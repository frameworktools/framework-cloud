// Shared by the project list and a project's own page

export const statusVariant = { pending: 'amber', running: 'amber', done: 'green', failed: 'red' };

export const statusLabel = { pending: 'Creating repository', running: 'Scaffolding on GitHub', done: 'Ready to clone', failed: 'Scaffold failed' };

export const typeName = (key) => key.split('_').map((w) => w[0].toUpperCase() + w.slice(1)).join(' ');
