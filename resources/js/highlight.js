// Syntax highlighting for short commands: only the languages Framework's
// clients use, so the bundle stays small
import hljs from 'highlight.js/lib/core';
import r from 'highlight.js/lib/languages/r';
import python from 'highlight.js/lib/languages/python';
import bash from 'highlight.js/lib/languages/bash';
import stata from 'highlight.js/lib/languages/stata';

hljs.registerLanguage('r', r);
hljs.registerLanguage('python', python);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('stata', stata);

/** Highlighted HTML for code, or null when the language isn't known. */
export const highlight = (code, language) =>
    language && hljs.getLanguage(language) ? hljs.highlight(code, { language, ignoreIllegals: true }).value : null;
