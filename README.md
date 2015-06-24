# gw2site
A leaflet-based web map for Guild Wars 2 that displays avatar positions as sent from the gw2server application.

The site first requires a basic password to get into, normally given out to the guild members who wish to use the application. The map itself uses the Leaflet library to display (original concept from the Guild Wars 2 forums when they released the map apis). A websocket is opened to the gw2server, and registers as a avatar consumer. Whenever avatar positions are updated, a message is sent to the site from the websocket server, and the site updates the map as necessary to add, update, or remove avatar positions. Since it is using websockets, the map updates in near real time.
