// Keyboard shortcuts as people on this computer write them: ⌘⇧S on a Mac, Ctrl+Shift+S elsewhere
const mac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

export function shortcutLabel(shortcut) {
    if (!shortcut) return '';
    const parts = shortcut.split('-').map(part => ({
        Mod: mac ? '⌘' : 'Ctrl', Shift: mac ? '⇧' : 'Shift', Alt: mac ? '⌥' : 'Alt',
    })[part] ?? part.toUpperCase());

    return parts.join(mac ? '' : '+');
}

// A link address from what someone typed: example.org becomes https://example.org, an email address becomes mailto:
export function normalizeLink(input) {
    let url = input.trim();
    if (!url) return { url: '' };
    if (!/^[a-z][a-z0-9+.-]*:/i.test(url)) url = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(url) ? `mailto:${url}` : `https://${url}`;
    if (!/^(https?:\/\/[^\s/]+\.[^\s]+|mailto:[^\s@]+@[^\s@]+\.[^\s@]+)$/i.test(url)) {
        return { error: 'Enter a web address, such as example.org, or an email address.' };
    }

    return { url };
}
