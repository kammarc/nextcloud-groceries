<?php

declare(strict_types=1);

return [
	'routes' => [
		// PWA shell, manifest and service worker (public: the app authenticates with an app password)
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#manifest', 'url' => '/manifest.webmanifest', 'verb' => 'GET'],
		['name' => 'page#serviceWorker', 'url' => '/sw.js', 'verb' => 'GET'],
	],
	'ocs' => [
		['name' => 'list_api#index', 'url' => '/api/v1/lists', 'verb' => 'GET'],
		['name' => 'list_api#create', 'url' => '/api/v1/lists', 'verb' => 'POST'],
		['name' => 'list_api#update', 'url' => '/api/v1/lists/{listId}', 'verb' => 'PUT'],
		['name' => 'list_api#destroy', 'url' => '/api/v1/lists/{listId}', 'verb' => 'DELETE'],
		['name' => 'list_api#members', 'url' => '/api/v1/lists/{listId}/members', 'verb' => 'GET'],
		['name' => 'list_api#setMembers', 'url' => '/api/v1/lists/{listId}/members', 'verb' => 'PUT'],
		['name' => 'list_api#leave', 'url' => '/api/v1/lists/{listId}/members/me', 'verb' => 'DELETE'],
		['name' => 'list_api#setPrefs', 'url' => '/api/v1/lists/{listId}/prefs', 'verb' => 'PUT'],
		['name' => 'list_api#setOrder', 'url' => '/api/v1/list-order', 'verb' => 'PUT'],
		['name' => 'list_api#searchPrincipals', 'url' => '/api/v1/principals', 'verb' => 'GET'],

		['name' => 'item_api#changes', 'url' => '/api/v1/lists/{listId}/changes', 'verb' => 'GET'],
		['name' => 'item_api#create', 'url' => '/api/v1/lists/{listId}/items', 'verb' => 'POST'],
		['name' => 'item_api#bulk', 'url' => '/api/v1/lists/{listId}/items/bulk', 'verb' => 'POST'],
		['name' => 'item_api#update', 'url' => '/api/v1/lists/{listId}/items/{itemId}', 'verb' => 'PATCH'],
		['name' => 'item_api#destroy', 'url' => '/api/v1/lists/{listId}/items/{itemId}', 'verb' => 'DELETE'],
		['name' => 'item_api#checkout', 'url' => '/api/v1/lists/{listId}/checkout', 'verb' => 'POST'],
		['name' => 'item_api#removeSource', 'url' => '/api/v1/lists/{listId}/sources/{source}/{sourceRef}', 'verb' => 'DELETE'],
		['name' => 'item_api#history', 'url' => '/api/v1/lists/{listId}/history', 'verb' => 'GET'],
		['name' => 'item_api#events', 'url' => '/api/v1/lists/{listId}/events', 'verb' => 'GET'],
		['name' => 'item_api#importEvents', 'url' => '/api/v1/lists/{listId}/events', 'verb' => 'POST'],
		['name' => 'item_api#forget', 'url' => '/api/v1/lists/{listId}/history/{historyId}', 'verb' => 'DELETE'],

		['name' => 'store_api#create', 'url' => '/api/v1/lists/{listId}/stores', 'verb' => 'POST'],
		['name' => 'store_api#update', 'url' => '/api/v1/lists/{listId}/stores/{storeId}', 'verb' => 'PUT'],
		['name' => 'store_api#destroy', 'url' => '/api/v1/lists/{listId}/stores/{storeId}', 'verb' => 'DELETE'],
		['name' => 'store_api#setOrder', 'url' => '/api/v1/lists/{listId}/stores/{storeId}/order', 'verb' => 'PUT'],
		['name' => 'store_api#createCategory', 'url' => '/api/v1/lists/{listId}/categories', 'verb' => 'POST'],
		['name' => 'store_api#deleteCategory', 'url' => '/api/v1/lists/{listId}/categories/{categoryKey}', 'verb' => 'DELETE'],

		['name' => 'catalog_api#catalog', 'url' => '/api/v1/catalog', 'verb' => 'GET'],
		['name' => 'catalog_api#parse', 'url' => '/api/v1/parse', 'verb' => 'POST'],
	],
];
