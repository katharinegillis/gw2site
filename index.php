<?php
// Grab the saved zoom and center position from any last activity on the client.
session_start();
if (!$_SESSION['loggedIn'] || (time() - $_SESSION['lastActivity']) > 30 * 60) {
	session_unset();
	session_destroy();
	header('location: login.php');
	exit;
}

$_SESSION['lastActivity'] = time();

$userZoom = !empty($_SESSION['zoom']) ? $_SESSION['zoom'] : null;
$userCenter = !empty($_SESSION['center']) ? $_SESSION['center'] : null;
//$userCenter = '[16384, 16384]';
?>
<html>
<head>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js"></script>
	<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.5.1/leaflet.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.5.1/leaflet.js"></script>
	<script src="leaflet.label.js"></script>
	<style>
	.leaflet-container {
	    background: #000;
	}

	#map {
	    position: absolute;
	    top: 0;
	    right: 0;
	    bottom: 0;
	    left: 0;
	}

	.leaflet-label {
		background: rgb(235, 235, 235);
		background: rgba(235, 235, 235, 0.81);
		background-clip: padding-box;
		border-color: #777;
		border-color: rgba(0,0,0,0.25);
		border-radius: 4px;
		border-style: solid;
		border-width: 4px;
		color: #111;
		display: block;
		font: 12px/20px "Helvetica Neue", Arial, Helvetica, sans-serif;
		font-weight: bold;
		padding: 1px 6px;
		position: absolute;
		-webkit-user-select: none;
		   -moz-user-select: none;
		    -ms-user-select: none;
		        user-select: none;
		white-space: nowrap;
		z-index: 6;
	}

	.leaflet-label:before {
		border-right: 6px solid black;
		border-right-color: inherit;
		border-top: 6px solid transparent;
		border-bottom: 6px solid transparent;
		content: "";
		position: absolute;
		top: 5px;
		left: -10px;
	}
	</style>
</head>
<body>
	<div id="map"></div>
	<script type="text/javascript">

	var map;
	var gameMaps = {}, mistsMaps = {};
	var playerMarkers = {};
	var playerMapIds = {};
	var playerTitles = {};
	var ajaxCallCount = 0;
	var userCenter, userZoom;

	var playerIcon, waypointIcon, richGoldOreIcon, richPlatinumOreIcon;

	var waypointLayer;
	var mistsWaypoints = [];
	var waypoints = [];
	var richOreLayer;
	var richOres = {gold: [], platinum: []};
	var playerLayer;
	var mapIdsContinentMap = {};

	var pveTileLayer, mistsTileLayer;
	var currentTileLayer = "Tyria";

	var pveSouthWest, pveNorthEast, mistsSouthWest, mistsNorthEast;

	function unproject(coord) {
        // Converts the coordinates from the units from the Gw2 api to that of the map.
	    return map.unproject(coord, map.getMaxZoom());
	}

	function onMapClick(e) {
        // Test function.
	    console.log("You clicked the map at " + map.project(e.latlng));
	}

	function getIconSize(zoom) {
        // Return an appropriate icon size based on the zoom.
		if (zoom == 3) {
			return [16, 16];
		} else if (zoom == 4) {
			return [20, 20];
		} else if (zoom == 5) {
			return [24, 24];
		} else if (zoom == 6) {
			return [28, 28];
		} else {
			return [32, 32];
		}
	}

	var offset = {
	    23: [0, 0],
	    34: [-64, 64],
	    50: [-64, 0],
	    91: [-64, 0],
	    35: [-64, 0],
	    54: [-64, 64],
	    15: [0, 64],
	    18: [-64, 0],
	    17: [-64, 0],
	    24: [-64, 0],
	    26: [0, 64],
	    326: [0, 64],
	    29: [0, 64],
	    39: [0, 64],
	    73: [-64, 64],
	    873: [-64, 0]
	}

	$(function () {
	    "use strict";

        // Set up the two map layer configurations (the first is the normal PvE map, the second is the combined WvW and sPvP map)
	    pveTileLayer = L.tileLayer("https://tiles.guildwars2.com/1/1/{z}/{x}/{y}.jpg", {
	        minZoom: 0,
	        maxZoom: 7,
	        continuousWorld: true,
	        name: "Tyria"
	    });

	    mistsTileLayer = L.tileLayer("https://tiles.guildwars2.com/2/1/{z}/{x}/{y}.jpg", {
	    	minZoom: 0,
	    	maxZoom: 7,
	    	continuousWorld: true,
	    	name: "Mists"
	    });

        // Set up a new map.
	    map = L.map("map", {
	        minZoom: 0,
	        maxZoom: 7,
	        crs: L.CRS.Simple,
	        layers: [pveTileLayer]
	    }).setView([0, 0], 5);

        // Mark out the corners of the map.
	    pveSouthWest = unproject([0, 32768]);
	    pveNorthEast = unproject([32768, 0]);
	    
	    map.setMaxBounds(new L.LatLngBounds(pveSouthWest, pveNorthEast));

        // Set up the layers that show the waypoints, the rich ors, and the players. The player one is the default.
	    waypointLayer = L.layerGroup();
	    richOreLayer = L.layerGroup();
	    playerLayer = L.layerGroup().addTo(map);

        // Add the two tile layers defined above, and the two non-default icon layers.
	    L.control.layers({"Tyria": pveTileLayer, "Mists": mistsTileLayer}, {"Waypoints" : waypointLayer, "Rich Ores" : richOreLayer}, {collapsed: false}).addTo(map);

        // Set the zoom and the center to that from the session, if it exists.
	    userZoom = <?php echo isset($userZoom) ? $userZoom : 0; ?>;
		userCenter = <?php echo isset($userCenter) ? $userCenter : '[16384, 16384]'; ?>;
		if (userCenter[0] == 16384 && userCenter[1] == 16384) {
			userCenter = unproject(userCenter);
		}

	    map.setView(userCenter, userZoom);

        // Handle the various map events.
	    map.on("click", onMapClick);
	    map.on("moveend", function() {
            // When the user stops moving the map, update the session's current zoom and center coordinates.
	    	userZoom = map.getZoom();
	    	var userCenterLatLng = map.getCenter();
	    	userCenter = [userCenterLatLng.lat, userCenterLatLng.lng];
	    	var userCenterStr = "[" + userCenter[0] + "," + userCenter[1] + "]";
	    	$.post('rest/session.php', {
	    		zoom: userZoom,
	    		center: userCenterStr
	    	});
	    });

	    map.on("zoomend", function() {
            // When the user stops changing the zoom level, update the various icons to use the correct size.
	    	var waypointIndex, richGoldOreIndex, richPlatinumOreIndex;

	    	waypointIcon = L.icon({
				iconUrl: "images/waypoint.png",
				iconSize: getIconSize(map.getZoom())
			});

	    	for (waypointIndex in waypoints) {
	    		waypoints[waypointIndex].setIcon(waypointIcon);
	    	}

	    	richGoldOreIcon = L.icon({
	    		iconUrl: "images/gold_ore.png",
	    		iconSize: getIconSize(map.getZoom())
	    	});

	    	for (richGoldOreIndex in richOres.gold) {
	    		richOres.gold[richGoldOreIndex].setIcon(richGoldOreIcon)
	    	}

	    	richPlatinumOreIcon = L.icon({
	    		iconUrl: "images/platinum_ore.png",
	    		iconSize: getIconSize(map.getZoom())
	    	});

	    	for (richPlatinumOreIndex in richOres.platinum) {
	    		richOres.platinum[richPlatinumOreIndex].setIcon(richPlatinumOreIcon)
	    	}
	    });

	    map.on("baselayerchange", function(ev) {
	    	var index;
	    	if (ev.layer.options.name == "Tyria") {
                // If the new layer is the PvE map, Tyria, set up the shown players as only those currently in Tyria not the Mists.
	    		currentTileLayer = "Tyria";
	    		playerLayer.clearLayers();
	    		for (index in playerMarkers) {
	    			if (mapIdsContinentMap[playerMapIds[index]] == "Tyria") {
	    				playerMarkers[index].addTo(playerLayer).showLabel();
	    			}
	    		}
                // Clear out the waypoints and add only those from Tyria.
	    		waypointLayer.clearLayers();
	    		for (index in waypoints) {
	    			waypoints[index].addTo(waypointLayer);
	    		}
                // Clear out the ores and add only those from Tyria.
	    		richOreLayer.clearLayers();
	    		for (index in richOres.gold) {
	    			richOres.gold[index].addTo(richOreLayer);
	    		}
	    		for (index in richOres.platinum) {
	    			richOres.platinum[index].addTo(richOreLayer);
	    		}
	    	} else {
                // If the new layer is the PvP map, the Mists, set up the shown players as only those currently in the Mists, not Tyria.
	    		currentTileLayer = "Mists";
	    		playerLayer.clearLayers();
	    		for (index in playerMarkers) {
	    			if (mapIdsContinentMap[playerMapIds[index]] == "Mists") {
	    				playerMarkers[index].addTo(playerLayer).showLabel();
	    			}
	    		}
                // Clear out the waypoints and add only those from the Mists.
	    		waypointLayer.clearLayers();
	    		for (index in mistsWaypoints) {
	    			mistsWaypoints[index].addTo(waypointLayer);
	    		}
                // Clear out the ores, there are none in the Mists.
	    		richOreLayer.clearLayers();
	    	}
	    });

        // Set up the icons for the default zoom.
		playerIcon = L.icon({
			iconUrl: "images/player.png",
			iconSize: [20, 20]
		});

		waypointIcon = L.icon({
			iconUrl: "images/waypoint.png",
			iconSize: getIconSize(userZoom)
		});

		richGoldOreIcon = L.icon({
			iconUrl: "images/gold_ore.png",
			iconSize: getIconSize(userZoom)
		});

		richPlatinumOreIcon = L.icon({
			iconUrl: "images/platinum_ore.png",
			iconSize: getIconSize(userZoom)
		});

        // Add in the known rich ore locations.
		richOres.gold.push(L.marker(unproject([31126, 12286]), { title: "Rich Gold Ore", icon: richGoldOreIcon }).addTo(richOreLayer));
		richOres.gold.push(L.marker(unproject([17909, 17982]), { title: "Rich Gold Ore", icon: richGoldOreIcon }).addTo(richOreLayer));
		richOres.gold.push(L.marker(unproject([31650, 17351]), { title: "Rich Gold Ore", icon: richGoldOreIcon }).addTo(richOreLayer));

		richOres.platinum.push(L.marker(unproject([27049, 10070]), { title: "Rich Platinum Ore", icon: richPlatinumOreIcon }).addTo(richOreLayer));
		richOres.platinum.push(L.marker(unproject([26930, 9851]), { title: "Rich Platinum Ore", icon: richPlatinumOreIcon }).addTo(richOreLayer));
		richOres.platinum.push(L.marker(unproject([27746, 9675]), { title: "Rich Platinum Ore", icon: richPlatinumOreIcon }).addTo(richOreLayer));
		richOres.platinum.push(L.marker(unproject([29083, 9609]), { title: "Rich Platinum Ore", icon: richPlatinumOreIcon }).addTo(richOreLayer));
		richOres.platinum.push(L.marker(unproject([27618, 11490]), { title: "Rich Platinum Ore", icon: richPlatinumOreIcon }).addTo(richOreLayer));
		richOres.platinum.push(L.marker(unproject([10467, 11257]), { title: "Rich Platinum Ore", icon: richPlatinumOreIcon }).addTo(richOreLayer));
		richOres.platinum.push(L.marker(unproject([17945, 22158]), { title: "Rich Platinum Ore", icon: richPlatinumOreIcon }).addTo(richOreLayer));
		richOres.platinum.push(L.marker(unproject([18174, 23326]), { title: "Rich Platinum Ore", icon: richPlatinumOreIcon }).addTo(richOreLayer));
		richOres.platinum.push(L.marker(unproject([19165, 19250]), { title: "Rich Platinum Ore", icon: richPlatinumOreIcon }).addTo(richOreLayer));
		richOres.platinum.push(L.marker(unproject([19918, 18466]), { title: "Rich Platinum Ore", icon: richPlatinumOreIcon }).addTo(richOreLayer));

        // Get the game map info for each tile layer.
		$.getJSON("https://api.guildwars2.com/v1/maps.json", function(data) {
			$.each(data.maps, function(mapId, map) {
				if (map.continent_id == 1) {
					gameMaps[mapId] = map;
					mapIdsContinentMap[mapId] = "Tyria";
				} else if (map.continent_id == 2) {
					mistsMaps[mapId] = map;
					mapIdsContinentMap[mapId] = "Mists";
				}
			});
		});

        // Get the waypoints for each tile layer and add them to the appropriate layers, then start the socket server connection.
	    $.getJSON("https://api.guildwars2.com/v1/map_floor.json?continent_id=1&floor=1", function (data) {
	        var region, gameMap, i, il, poi;
	        
	        for (region in data.regions) {
	            region = data.regions[region];
	            
	            for (gameMap in region.maps) {
	            	gameMap = region.maps[gameMap];

	            	for (i = 0, il = gameMap.points_of_interest.length; i < il; i++) {
	            		poi = gameMap.points_of_interest[i];

	            		if (poi.type != "waypoint") {
	            			continue;
	            		}

	            		waypoints.push(L.marker(unproject(poi.coord), { title: poi.name, icon: waypointIcon }).addTo(waypointLayer));
	            	}
	            }
	        }

	        $.getJSON("https://api.guildwars2.com/v1/map_floor.json?continent_id=2&floor=1", function(data) {
	        	var region, gameMap, i, il, poi;

	        	for (region in data.regions) {
	        		region = data.regions[region];

	        		for (gameMap in region.maps) {
	        			gameMap = region.maps[gameMap];

	        			for (i = 0, il = gameMap.points_of_interest.length; i < il; i++) {
	            		poi = gameMap.points_of_interest[i];

	            		if (poi.type != "waypoint") {
	            			continue;
	            		}

	            		poi.coord[0] = poi.coord[0] * 2;
	            		poi.coord[1] = poi.coord[1] * 2;

	            		mistsWaypoints.push(L.marker(unproject(poi.coord), { title: poi.name, icon: waypointIcon }));
	            	}
	        		}
	        	}

	        	StartSocket();
	        });
	    });
	});

	function StartSocket() {
        // Open a new socket to the websocket server.
		var conn = new WebSocket("ws://www.tichi.org:44791");
		conn.onopen = function(e) {
            // On connect, send the register request.
			console.log("Connected.");
			conn.send(JSON.stringify({
				"method": "register",
				"type": "AvatarConsumer"
			}));
		}

		conn.onclose = function(e) {
            // Notify when the connection is closed by the server.
			console.log("Closed.");
		}

		conn.onerror = function(e) {
            // Notify when there is an error.
			console.log(e);
		}

		conn.onmessage = function(e) {
            // Parse the message from the websocket server.
			var data = JSON.parse(e.data);

			if (data.method == "sendAvatars") {
                // If avatar positions were sent, remove all the player markers and re-add them in the newly updated positions.
				$.each(data.avatars, function(guid, avatar) {
					var coor = TranslatePlayerCoordinates(avatar.mapId, avatar.x, avatar.y);
					if (coor == false) {
						map.removeLayer(playerMarkers[guid]);
						delete playerMarkers[guid];
					} else {
						if (playerMarkers.hasOwnProperty(guid)) {
							if (playerTitles[guid] == avatar.name && mapIdsContinentMap[avatar.mapId] == currentTileLayer) {
								playerMarker = playerMarkers[guid];
								playerMarker.setLatLng(unproject(coor));
								playerMarker.update();
								playerMapIds[guid] = avatar.mapId;
							} else {
								map.removeLayer(playerMarkers[guid]);
								delete playerMarkers[guid];

								playerMarker = L.marker(unproject(coor), {title: avatar.name, icon: playerIcon}).bindLabel(avatar.name, { noHide: true });
								if (mapIdsContinentMap[avatar.mapId] == currentTileLayer) {
									playerMarker.addTo(playerLayer).showLabel();
								}
								playerMarkers[guid] = playerMarker;
								playerMapIds[guid] = avatar.mapId;
								playerTitles[guid] = avatar.name;
							}
						} else {
							playerMarker = L.marker(unproject(coor), {title: avatar.name, icon: playerIcon}).bindLabel(avatar.name, { noHide: true });
							if (mapIdsContinentMap[avatar.mapId] == currentTileLayer) {
								playerMarker.addTo(playerLayer).showLabel();
							}
							playerMarkers[guid] = playerMarker;
							playerMapIds[guid] = avatar.mapId;
							playerTitles[guid] = avatar.name;
						}
					}
				});
			} else if (data.method == "removeAvatars") {
                // Remove each avatar by guid sent from the websocket server.
				$.each(data.avatars, function(guid, avatar) {
					map.removeLayer(playerMarkers[guid]);
					delete playerMarkers[guid];
				});
			}
		}
	}

	function TranslatePlayerCoordinates(mapId, x, y) {
        // Translate the coordinates from what is sent by the websocket server to what is expected for the map. This includes the slight offset discovered on each map.
		var coor = [x, -1*y];
		var mapNum = mapId;

		if (!mapIdsContinentMap.hasOwnProperty(mapNum)) {
			return false;
		}

		var gameMap;
		if (mapIdsContinentMap[mapNum] == "Tyria") {
			gameMap = gameMaps[mapNum];
		} else {
			gameMap = mistsMaps[mapNum];
		}

		// Convert to continent_rect off of center.
		var xRatio = (gameMap.map_rect[1][0] - gameMap.map_rect[0][0]) / (gameMap.continent_rect[1][0] - gameMap.continent_rect[0][0]);
		var yRatio = (gameMap.map_rect[1][1] - gameMap.map_rect[0][1]) / (gameMap.continent_rect[1][1] - gameMap.continent_rect[0][1]);

		coor[0] = coor[0] / xRatio/* + offsetMap[mapNum][0]*/;
		coor[1] = coor[1] / yRatio/* + offsetMap[mapNum][1]*/;

		// Move to absolute from 0, 0 center.
		var xCenter = (gameMap.continent_rect[1][0] - gameMap.continent_rect[0][0]) / 2 + gameMap.continent_rect[0][0];
		var yCenter = (gameMap.continent_rect[1][1] - gameMap.continent_rect[0][1]) / 2 + gameMap.continent_rect[0][1];

		coor[0] = coor[0] + xCenter + GetOffset(mapNum, 'x');
		coor[1] = coor[1] + yCenter + GetOffset(mapNum, 'y');

		if (mapIdsContinentMap[mapNum] == "Mists") {
			coor[0] = coor[0] * 2;
			coor[1] = coor[1] * 2;
		}

		return coor;
	}

	function GetOffset(map_id, dir) {
        // Get the offset for the particular map, or 0 if the map is not in the array. Offsets are hardcoded for the moment.
		if (!offset.hasOwnProperty(map_id)) {
			return 0;
		}

		if (dir == 'x') {
			return offset[map_id][0];
		} else {
			return offset[map_id][1];
		}
	}
	</script>
</body>
</html>