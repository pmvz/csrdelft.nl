const PUSH_REFRESH = 'pushRefresh';
const CACHE_NAME = 'offline';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
	event.waitUntil(
		(async () => {
			const cache = await caches.open(CACHE_NAME);
			// Cache 'reload' is om alleen cache van het netwerk te laden
			await cache.add(new Request(OFFLINE_URL, { cache: 'reload' }));
		})()
	);

	// Wacht niet op handmatig registreren
	self.skipWaiting();
});

self.addEventListener('activate', (event) => {
	event.waitUntil(
		(async () => {
			if ('navigationPreload' in self.registration) {
				await self.registration.navigationPreload.enable();
			}
		})()
	);

	self.clients.claim();
});

self.addEventListener('fetch', (event) => {
	// Kijk of de gebruiker een nieuwe pagina laad
	if (event.request.mode === 'navigate') {
		event.respondWith(
			(async () => {
				try {
					const preloadResponse = await event.preloadResponse;
					if (preloadResponse) {
						return preloadResponse;
					}

					// Nog even proberen
					const networkResponse = await fetch(event.request);
					return networkResponse;
				} catch (error) {
					// Als we hier zijn is er geen netwerkverbinding
					console.log('Gebruiker is offline: ', error);

					const cache = await caches.open(CACHE_NAME);
					const cachedResponse = await cache.match(OFFLINE_URL);
					return cachedResponse;
				}
			})()
		);
	} else {
		console.log(`[Service Worker] Fetched resource ${e.request.url}`);
	}
});

// Laat een notificatie zien als het een bericht krijgt van de WebPush API
self.addEventListener('push', (event) => {
	let messageData = event.data.json();

	event.waitUntil(
		self.registration.showNotification(messageData.title, {
			tag: messageData.tag,
			body: messageData.body,
			icon: '/favicon.ico',
			image: messageData.image,
			data: messageData.url,
			actions: [
				{
					action: messageData.url,
					title: 'Open url',
				},
			],
		})
	);
});

// Om de link van het stek bericht in de browser te openen
self.addEventListener(
	'notificationclick',
	async function (event) {
		event.notification.close();

		if (clients.openWindow) {
			if (event.action) {
				// Als er een actie is geklikt, open deze
				clients.openWindow(event.action);
			} else {
				clients.openWindow(event.notification.data);
			}
		}
	},
	false
);

// Als de browser de subscription update, moet het ook in de server gezet worden
self.addEventListener(
	'pushsubscriptionchange',
	(event) => {
		event.waitUntil(
			swRegistration.pushManager
				.subscribe(event.oldSubscription.options)
				.then((subscription) => {
					const pushRefresh = localStorage.getItem(PUSH_REFRESH);
					return fetch('/subscription', {
						method: 'PATCH',
						body: JSON.stringify({
							id: pushRefresh,
							...subscription.toJSON(),
						}),
						headers: {
							'content-type': 'application/json',
						},
					});
				})
		);
	},
	false
);
