// Shared by the tabs that edit project settings: Quarto theme and
// highlighting names as the Framework GUI showed them, and the editor-file
// choices.
const titled = (value) => value.charAt(0).toUpperCase() + value.slice(1);

export const themeLabel = titled;

export const highlightLabel = (value) => (value === 'github' ? 'GitHub' : value === 'breezedark' ? 'Breeze Dark' : titled(value));

// Editor files: RStudio's .Rproj and VS Code and Positron's .code-workspace,
// saved as the one value R and Python read (positron means vscode)
export const editorOptions = [
    { value: 'rstudio', label: 'RStudio', description: 'Adds a .Rproj file to R projects.' },
    { value: 'vscode', label: 'Positron / VS Code', description: 'Adds a .code-workspace file.' },
];
export const editorsOf = (ide) => ({ rstudio: ['rstudio'], vscode: ['vscode'], positron: ['vscode'], both: ['rstudio', 'vscode'] })[ide] ?? [];
export const ideOf = (list) => {
    const rstudio = list.includes('rstudio');
    const vscode = list.includes('vscode');
    return rstudio && vscode ? 'both' : rstudio ? 'rstudio' : vscode ? 'vscode' : 'none';
};
