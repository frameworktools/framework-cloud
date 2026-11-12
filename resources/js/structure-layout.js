export function compareFolders(a, b) {
    const left = a.path.split('/');
    const right = b.path.split('/');
    for (let i = 0; i < Math.min(left.length, right.length); i++) {
        const order = left[i].localeCompare(right[i], undefined, { numeric: true });
        if (order) return order;
    }
    return left.length - right.length;
}

export const within = (path, parent) => path === parent || path.startsWith(`${parent}/`);
export const rewrite = (path, from, to) => within(path, from) ? to + path.slice(from.length) : path;

export function moveFolder(folders, locations, files, from, to) {
    if (!to || to.split('/').some((part) => !part || part === '.' || part === '..') || /[\\\x00-\x1f]/.test(to)) throw new Error('Use a relative folder path without empty, . or .. segments.');
    if (to !== from && within(to, from)) throw new Error('A folder cannot contain itself.');
    const moved = folders.map((folder) => ({ ...folder, path: rewrite(folder.path, from, to) }));
    if (new Set(moved.map((folder) => folder.path)).size !== moved.length) throw new Error('A folder already uses that path.');
    return {
        folders: moved,
        locations: Object.fromEntries(Object.entries(locations).map(([key, path]) => [key, path ? rewrite(path, from, to) : path])),
        files: files.map((file) => ({ ...file, path: rewrite(file.path, from, to) })),
    };
}
