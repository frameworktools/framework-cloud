import { reactive } from 'vue';

// One confirmation dialog for the whole app, in place of window.confirm.
// ask() opens it and resolves true when the person confirms. The button
// takes its label from the question's first words unless one is given.
export const confirmState = reactive({ open: false, title: '', message: '', action: 'Confirm', danger: true, resolve: null });

const actions = [
    [/^Take down/, 'Take down', true],
    [/^Turn off/, 'Turn off', true],
    [/^Revoke/, 'Revoke', true],
    [/^Delete/, 'Delete', true],
    [/^Remove/, 'Remove', true],
    [/^Disconnect/, 'Disconnect', true],
    [/^Replace/, 'Replace', true],
    [/^Append/, 'Merge versions', false],
    [/^Publish/, 'Publish', false],
    [/^Restore/, 'Restore', false],
];

export function ask(message, options = {}) {
    const [, action, danger] = actions.find(([pattern]) => pattern.test(message)) ?? [null, 'Confirm', false];
    // A dialog still open answers no before the next one opens
    confirmState.resolve?.(false);
    return new Promise((resolve) => {
        Object.assign(confirmState, { open: true, title: options.title ?? '', message, action: options.action ?? action, danger: options.danger ?? danger, resolve });
    });
}

export function answer(value) {
    const resolve = confirmState.resolve;
    Object.assign(confirmState, { open: false, resolve: null });
    resolve?.(value);
}
