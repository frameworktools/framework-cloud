// Shared by the tabs that edit project settings: Quarto theme and
// highlighting names as the Framework GUI showed them, rough theme colors
// for a small preview, and the editor-file choices.
const titled = (value) => value.charAt(0).toUpperCase() + value.slice(1);

export const themeLabel = titled;

export const highlightLabel = (value) => (value === 'github' ? 'GitHub' : value === 'breezedark' ? 'Breeze Dark' : titled(value));

// Each theme's background, text, and accent, roughly
export const htmlThemeColors = {
    default: ['#ffffff', '#212529', '#0d6efd'], cosmo: ['#ffffff', '#373a3c', '#2780e3'], cerulean: ['#ffffff', '#495057', '#2fa4e7'],
    flatly: ['#ffffff', '#212529', '#2c3e50'], journal: ['#ffffff', '#222222', '#eb6864'], lumen: ['#ffffff', '#222222', '#158cba'],
    minty: ['#ffffff', '#888888', '#78c2ad'], pulse: ['#ffffff', '#444444', '#593196'], sandstone: ['#ffffff', '#3e3f3a', '#325d88'],
    simplex: ['#fcfcfc', '#212529', '#d9230f'], sketchy: ['#ffffff', '#212529', '#333333'], slate: ['#272b30', '#aaaaaa', '#3a3f44'],
    solar: ['#002b36', '#839496', '#b58900'], spacelab: ['#ffffff', '#777777', '#446e9b'], superhero: ['#0f2537', '#ebebeb', '#df6919'],
    united: ['#ffffff', '#333333', '#e95420'], yeti: ['#ffffff', '#222222', '#008cba'],
};

export const slideThemeColors = {
    default: ['#ffffff', '#222222', '#2a76dd'], beige: ['#f7f2d3', '#333333', '#8b743d'], black: ['#191919', '#ffffff', '#42affa'],
    blood: ['#222222', '#eeeeee', '#aa2233'], league: ['#2b2b2b', '#eeeeee', '#13daec'], moon: ['#002b36', '#93a1a1', '#268bd2'],
    night: ['#111111', '#eeeeee', '#e7ad52'], serif: ['#f0f1eb', '#000000', '#51483d'], simple: ['#ffffff', '#000000', '#00008b'],
    sky: ['#add9e4', '#333333', '#3b759e'], solarized: ['#fdf6e3', '#657b83', '#268bd2'], white: ['#ffffff', '#222222', '#2a76dd'],
};

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
