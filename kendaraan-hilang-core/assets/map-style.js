/* Gaya peta vektor "Ingress dark" untuk MapLibre GL.
   Sumber: OpenFreeMap (OpenStreetMap, tanpa API key). */
window.KH_MAP_STYLE = {
	version: 8,
	name: 'KM Ingress Dark',
	glyphs: 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf',
	/* sprite tidak dipakai: tidak ada layer icon-image → hemat ±117 KB */
	sources: {
		openmaptiles: { type: 'vector', url: 'https://tiles.openfreemap.org/planet' }
	},
	layers: [
		{ id: 'bg', type: 'background', paint: { 'background-color': '#030a12' } },

		/* daratan / tutupan lahan */
		{ id: 'landuse', type: 'fill', source: 'openmaptiles', 'source-layer': 'landuse',
			paint: { 'fill-color': '#061019', 'fill-opacity': 0.85 } },
		{ id: 'landcover', type: 'fill', source: 'openmaptiles', 'source-layer': 'landcover',
			paint: { 'fill-color': '#07141b', 'fill-opacity': 0.55 } },
		{ id: 'park', type: 'fill', source: 'openmaptiles', 'source-layer': 'park',
			paint: { 'fill-color': '#08171d' } },

		/* air */
		{ id: 'water', type: 'fill', source: 'openmaptiles', 'source-layer': 'water',
			paint: { 'fill-color': '#010509' } },
		{ id: 'waterway', type: 'line', source: 'openmaptiles', 'source-layer': 'waterway',
			paint: { 'line-color': '#0a2b38', 'line-width': ['interpolate', ['linear'], ['zoom'], 8, 0.6, 16, 2.4] } },

		/* bangunan (zoom dekat) */
		{ id: 'building', type: 'fill', source: 'openmaptiles', 'source-layer': 'building', minzoom: 13.5,
			paint: { 'fill-color': '#0b1a22', 'fill-opacity': 0.9, 'fill-outline-color': '#12303a' } },

		/* jalan — efek glow: lebar & redup di bawah, tipis & terang di atas */
		{ id: 'road-glow-major', type: 'line', source: 'openmaptiles', 'source-layer': 'transportation',
			filter: ['all', ['==', ['geometry-type'], 'LineString'], ['in', ['get', 'class'], ['literal', ['motorway', 'trunk']]]],
			paint: { 'line-color': '#35f0c8', 'line-opacity': 0.10,
				'line-width': ['interpolate', ['linear'], ['zoom'], 5, 2, 10, 8, 16, 22] } },
		{ id: 'road-motorway', type: 'line', source: 'openmaptiles', 'source-layer': 'transportation',
			filter: ['all', ['==', ['geometry-type'], 'LineString'], ['in', ['get', 'class'], ['literal', ['motorway', 'trunk', 'motorway_link', 'trunk_link']]]],
			paint: { 'line-color': '#35f0c8', 'line-opacity': 0.42,
				'line-width': ['interpolate', ['linear'], ['zoom'], 5, 0.6, 10, 1.8, 16, 6] } },
		{ id: 'road-primary', type: 'line', source: 'openmaptiles', 'source-layer': 'transportation',
			filter: ['all', ['==', ['geometry-type'], 'LineString'], ['in', ['get', 'class'], ['literal', ['primary', 'secondary', 'primary_link', 'secondary_link']]]],
			paint: { 'line-color': '#2fb4ff', 'line-opacity': 0.30,
				'line-width': ['interpolate', ['linear'], ['zoom'], 7, 0.4, 12, 1.4, 16, 4.2] } },
		{ id: 'road-minor', type: 'line', source: 'openmaptiles', 'source-layer': 'transportation',
			filter: ['all', ['==', ['geometry-type'], 'LineString'], ['in', ['get', 'class'], ['literal', ['tertiary', 'tertiary_link', 'minor', 'service', 'residential', 'unclassified']]]],
			paint: { 'line-color': '#6fe0cf', 'line-opacity': 0.13,
				'line-width': ['interpolate', ['linear'], ['zoom'], 12, 0.3, 16, 1.6] } },
		{ id: 'rail', type: 'line', source: 'openmaptiles', 'source-layer': 'transportation',
			filter: ['all', ['==', ['geometry-type'], 'LineString'], ['==', ['get', 'class'], 'rail']],
			paint: { 'line-color': '#2fb4ff', 'line-opacity': 0.32, 'line-width': 1.2, 'line-dasharray': [2, 2] } },

		/* batas administrasi */
		{ id: 'boundary', type: 'line', source: 'openmaptiles', 'source-layer': 'boundary',
			filter: ['all', ['==', ['geometry-type'], 'LineString'], ['<=', ['get', 'admin_level'], 6]],
			paint: { 'line-color': '#2fb4ff', 'line-opacity': 0.18, 'line-width': 1, 'line-dasharray': [3, 4] } },

		/* label */
		{ id: 'water-name', type: 'symbol', source: 'openmaptiles', 'source-layer': 'water_name',
			layout: { 'text-field': ['coalesce', ['get', 'name:latin'], ['get', 'name']], 'text-font': ['Noto Sans Regular'],
				'text-size': 11, 'text-letter-spacing': 0.15 }, minzoom: 8,
			paint: { 'text-color': '#2f6f7a', 'text-halo-color': '#030a12', 'text-halo-width': 1.2 } },

		{ id: 'road-label', type: 'symbol', source: 'openmaptiles', 'source-layer': 'transportation_name',
			layout: { 'text-field': ['coalesce', ['get', 'name:latin'], ['get', 'name']], 'text-font': ['Noto Sans Regular'],
				'text-size': 10, 'text-letter-spacing': 0.06, 'symbol-placement': 'line', 'text-max-angle': 30 },
			minzoom: 12,
			paint: { 'text-color': '#4b8b93', 'text-halo-color': '#030a12', 'text-halo-width': 1.4 } },

		{ id: 'place-village', type: 'symbol', source: 'openmaptiles', 'source-layer': 'place',
			filter: ['in', ['get', 'class'], ['literal', ['village', 'hamlet', 'suburb', 'quarter', 'neighbourhood']]],
			layout: { 'text-field': ['coalesce', ['get', 'name:latin'], ['get', 'name']], 'text-font': ['Noto Sans Regular'],
				'text-size': ['interpolate', ['linear'], ['zoom'], 10, 9, 15, 12], 'text-letter-spacing': 0.14,
				'text-transform': 'uppercase' },
			paint: { 'text-color': '#3d6a72', 'text-halo-color': '#030a12', 'text-halo-width': 1.2 } },

		{ id: 'place-town', type: 'symbol', source: 'openmaptiles', 'source-layer': 'place',
			filter: ['in', ['get', 'class'], ['literal', ['town']]],
			layout: { 'text-field': ['coalesce', ['get', 'name:latin'], ['get', 'name']], 'text-font': ['Noto Sans Bold'],
				'text-size': ['interpolate', ['linear'], ['zoom'], 6, 11, 12, 15], 'text-letter-spacing': 0.2,
				'text-transform': 'uppercase' },
			paint: { 'text-color': '#57a3a8', 'text-halo-color': '#030a12', 'text-halo-width': 1.6 } },

		{ id: 'place-city', type: 'symbol', source: 'openmaptiles', 'source-layer': 'place',
			filter: ['in', ['get', 'class'], ['literal', ['city']]],
			layout: { 'text-field': ['coalesce', ['get', 'name:latin'], ['get', 'name']], 'text-font': ['Noto Sans Bold'],
				'text-size': ['interpolate', ['linear'], ['zoom'], 4, 13, 10, 20], 'text-letter-spacing': 0.24,
				'text-transform': 'uppercase' },
			paint: { 'text-color': '#6fd8db', 'text-halo-color': '#02121b', 'text-halo-width': 2 } }
	]
};
