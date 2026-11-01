// The coding-agent choices guided setup and the Agents page share. They're
// saved as agent setup on or off plus the named agents: Other agents is
// AGENTS.md alone, which every agent choice includes, and None turns setup
// off without forgetting which agents were saved.
export const NAMED = ['claude', 'codex', 'copilot'];
export const agentNames = { claude: 'Claude Code', codex: 'Codex', copilot: 'GitHub Copilot', other: 'Other agents', none: 'None' };

/** The choices for what's saved. */
export const toChoices = (enabled, agents) => (enabled ? [...agents.filter((a) => NAMED.includes(a)), 'other'] : ['none']);

/** The next choices after a click: None stands alone, and AGENTS.md comes with any agent. */
export const pick = (previous, picked) => {
    const added = picked.find((a) => !previous.includes(a));
    if (added === 'none') return ['none'];
    const named = picked.filter((a) => NAMED.includes(a));
    // Unticking the last choice leaves None
    if (!named.length && !picked.includes('other')) return ['none'];
    return [...named, 'other'];
};

/** What to save for the choices. */
export const fromChoices = (choices) => ({ enabled: !choices.includes('none'), agents: choices.filter((a) => NAMED.includes(a)) });

export const agentOptions = (choices) => [
    { value: 'claude', label: 'Claude Code' },
    { value: 'codex', label: 'Codex' },
    { value: 'copilot', label: 'GitHub Copilot' },
    {
        value: 'other', label: 'Other agents',
        description: choices.some((a) => NAMED.includes(a)) ? 'Included: AGENTS.md comes with every agent.' : 'Any agent that reads AGENTS.md.',
        disabled: choices.some((a) => NAMED.includes(a)),
    },
    { value: 'none', label: 'None', description: 'No agent files or skills.' },
];
