// Resolved blueprint previews, kept for the page's life so a card opens at
// once. A cached one shows straight away and is checked again in the background.
const cache = new Map();
const pending = new Map();

const keyOf = (url, body) => `${url} ${JSON.stringify(body)}`;

export const cachedPreview = (url, body) => cache.get(keyOf(url, body));

export const fetchPreview = (url, body, csrf) => {
    const key = keyOf(url, body);
    if (pending.has(key)) return pending.get(key);
    const request = fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    })
        .then(async (response) => {
            if (!response.ok) return { available: false, problem: "The preview couldn't be loaded." };
            const data = await response.json();
            cache.set(key, data);
            return data;
        })
        .finally(() => pending.delete(key));
    pending.set(key, request);
    return request;
};

// Start loading before the click: on hover or focus
export const prefetchPreview = (url, body, csrf) => {
    if (!cache.has(keyOf(url, body))) fetchPreview(url, body, csrf).catch(() => {});
};
