// Shared class recipes for form controls, after Flux's outline style (see .claude/skills/cloud-design).
// Components use these; a page that needs a bare control uses them instead of inventing its own.
export const controlClass = 'block w-full rounded-lg border border-slate-200 border-b-slate-300/80 bg-white text-base text-slate-800 shadow-xs placeholder:text-slate-400 '
    + 'focus:border-slate-300 focus:outline-2 focus:outline-offset-2 focus:outline-sky-600 disabled:opacity-60 sm:text-sm '
    + 'dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:outline-sky-500 '
    + 'aria-invalid:border-red-500 dark:aria-invalid:border-red-500';
export const inputClass = `${controlClass} h-10 px-3`;
export const selectClass = `${controlClass} h-10 pl-3 pr-9`;
export const textareaClass = `${controlClass} px-3 py-2 leading-relaxed`;
export const labelClass = 'text-sm font-medium text-slate-800 dark:text-white';
export const descriptionClass = 'text-sm text-slate-500 dark:text-slate-400';
export const errorClass = 'text-sm text-red-600 dark:text-red-400';
