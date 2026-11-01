// The coding-agent choices guided setup and the Agents page share. They're
// saved as agent setup on or off, the named agents, and whether the person
// uses other agents. Every choice but None writes AGENTS.md, which other
// agents read, so Other agents changes no files; it records the answer.
// None turns setup off without forgetting the saved agents.
export const NAMED = ['claude', 'codex', 'copilot'];
export const agentNames = { claude: 'Claude Code', codex: 'Codex', copilot: 'GitHub Copilot', other: 'Other agents', none: 'None' };

/** The choices for what's saved. Setup on with no agent named means other agents. */
export const toChoices = (enabled, agents, other) => {
    if (!enabled) return ['none'];
    const named = agents.filter((a) => NAMED.includes(a));
    return other || !named.length ? [...named, 'other'] : named;
};

/** The next choices after a click: None stands alone, and unticking everything leaves None. */
export const pick = (previous, picked) => {
    const added = picked.find((a) => !previous.includes(a));
    if (added === 'none') return ['none'];
    const rest = picked.filter((a) => a !== 'none');
    return rest.length ? rest : ['none'];
};

/** What to save for the choices. */
export const fromChoices = (choices) => ({
    enabled: !choices.includes('none'),
    agents: choices.filter((a) => NAMED.includes(a)),
    other: choices.includes('other'),
});

export const agentOptions = [
    { value: 'claude', label: 'Claude Code' },
    { value: 'codex', label: 'Codex' },
    { value: 'copilot', label: 'GitHub Copilot' },
    { value: 'other', label: 'Other agents', description: 'Any other agent that reads AGENTS.md.' },
    { value: 'none', label: 'None', description: 'No agent files or skills.' },
];
