// Shared by the project list and a project's own page

export const statusVariant = { pending: 'amber', running: 'amber', done: 'green', failed: 'red' };

export const statusLabel = { pending: 'Creating repository', running: 'Scaffolding on GitHub', done: 'Ready to clone', failed: 'Scaffold failed' };

// The structure names people pick in setup, so a list never shows "Project Sensitive"
const typeNames = { project: 'Standard', project_sensitive: 'Privacy sensitive', presentation: 'Presentation', course: 'Course', bare: 'Bare' };
export const typeName = (key) => typeNames[key] ?? key.split('_').map((w) => w[0].toUpperCase() + w.slice(1)).join(' ');
