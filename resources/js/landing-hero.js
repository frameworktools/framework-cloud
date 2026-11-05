const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
for (const root of document.querySelectorAll('[data-landing-demo]')) {
    const slides = [...root.querySelectorAll('[data-demo-slide]')];
    const languageTabs = [...root.querySelectorAll('[data-demo-language]')];
    const pause = root.querySelector('[data-demo-pause]');
    const status = root.querySelector('[data-demo-status]');
    let current = 0, language = 'r', stopped = reducedMotion.matches || window.innerWidth < 768;
    const show = index => {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            slide.hidden = i !== current;
            slide.querySelectorAll('[data-demo-code]').forEach(code => { code.hidden = code.dataset.demoCode !== language; });
        });
        root.querySelectorAll('[data-demo-select]').forEach(button => button.setAttribute('aria-pressed', String(Number(button.dataset.demoSelect) === current)));
        root.querySelector('[data-demo-title]').textContent = root.querySelector('[data-demo-view="result"]').getAttribute('aria-pressed') === 'true' ? 'A consistent structure for every project' : slides[current].getAttribute('aria-label');
        status.textContent = `${slides[current].getAttribute('aria-label')} example`;
    };
    root.querySelectorAll('[data-demo-view]').forEach(button => button.addEventListener('click', () => {
        stop();
        if (button.dataset.demoView === 'result') root.querySelector('[data-demo-title]').textContent = 'A consistent structure for every project';
        else root.querySelector('[data-demo-title]').textContent = slides[current].getAttribute('aria-label');
        root.querySelectorAll('[data-demo-view]').forEach(other => other.setAttribute('aria-pressed', String(other === button)));
        root.querySelectorAll('[data-demo-view-panel]').forEach(panel => { panel.hidden = panel.dataset.demoViewPanel !== button.dataset.demoView; });
    }));
    const updatePause = () => { pause.setAttribute('aria-label', stopped ? 'Play examples' : 'Pause examples'); pause.setAttribute('aria-pressed', String(stopped)); };
    const stop = () => { stopped = true; status.setAttribute('aria-live', 'polite'); updatePause(); };
    const setLanguage = value => {
        language = value;
        root.querySelector(`[data-project-language="${language === 'python' ? 'py' : language}"]`)?.click();
        root.querySelector('#demo-code').setAttribute('aria-labelledby', `demo-language-${language}`);
        languageTabs.forEach(tab => { const active = tab.dataset.demoLanguage === language; tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1; });
        show(current);
    };
    languageTabs.forEach((tab, index) => {
        tab.addEventListener('click', () => { stop(); setLanguage(tab.dataset.demoLanguage); });
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault(); stop();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? languageTabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + languageTabs.length) % languageTabs.length;
            setLanguage(languageTabs[next].dataset.demoLanguage); languageTabs[next].focus();
        });
    });
    root.querySelectorAll('[data-demo-select]').forEach(button => button.addEventListener('click', () => { stop(); show(Number(button.dataset.demoSelect)); }));
    root.querySelector('[data-demo-previous]').addEventListener('click', () => { stop(); show(current - 1); });
    root.querySelector('[data-demo-next]').addEventListener('click', () => { stop(); show(current + 1); });
    pause.addEventListener('click', () => { stopped = !stopped; updatePause(); });
    reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) stop(); });
    setInterval(() => { if (!stopped && !document.hidden && !root.matches(':hover') && !root.contains(document.activeElement)) show(current + 1); }, 7000);
    show(current); updatePause();
}
// Native details retain keyboard access and work without JavaScript.
for (const root of document.querySelectorAll('[data-setup-tabs]')) {
    const tabs = [...root.querySelectorAll('[data-setup-tab]')];
    const select = tab => {
        const key = tab.dataset.setupTab;
        tabs.forEach(other => { const active = other === tab; other.setAttribute('aria-selected', String(active)); other.tabIndex = active ? 0 : -1; });
        root.querySelectorAll('[data-setup-panel]').forEach(panel => { panel.hidden = panel.dataset.setupPanel !== key; });
        root.querySelectorAll('[data-setup-path]').forEach(path => { path.hidden = path.dataset.setupPath !== key; });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(tab));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = tabs[event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];
            select(next); next.focus();
        });
    });
}

for (const menu of document.querySelectorAll('[data-site-menu]')) {
    menu.addEventListener('toggle', () => { if (menu.open) document.querySelectorAll('[data-site-menu]').forEach(other => { if (other !== menu) other.open = false; }); });
    menu.addEventListener('keydown', event => { if (event.key === 'Escape') { menu.open = false; menu.querySelector('summary').focus(); } });
    document.addEventListener('click', event => { if (!menu.contains(event.target)) menu.open = false; });
}

// Highlighted, escaped source is shared across examples instead of repeated in the page.
const projectSources = JSON.parse(document.querySelector('#landing-project-sources')?.textContent || '{}');
// Each example is a read-only project with language-specific files.
for (const root of document.querySelectorAll('[data-project-editor]')) {
    let language = root.querySelector('[data-project-language]').dataset.projectLanguage;
    let file = 'main';
    const render = () => {
        const matchesVariant = panel => !root.dataset.projectVariant || panel.dataset.projectVariant === root.dataset.projectVariant;
        const available = [...root.querySelectorAll('[data-project-source]')].filter(panel => panel.dataset.projectSourceLanguage === language && matchesVariant(panel));
        if (!available.some(panel => panel.dataset.projectSource === file)) file = 'main';
        root.querySelectorAll('[data-project-source]').forEach(panel => {
            const active = panel.dataset.projectSourceLanguage === language && panel.dataset.projectSource === file && matchesVariant(panel);
            panel.hidden = !active;
            if (active) {
                root.querySelector('[data-project-path]').textContent = panel.dataset.projectSourcePath;
                const terminal = root.querySelector('[data-project-terminal]');
                if (terminal) terminal.hidden = !/console|^Terminal$/i.test(panel.dataset.projectSourcePath);
                const code = panel.querySelector('code');
                if (panel.dataset.projectContent && !code.childElementCount) code.innerHTML = projectSources[panel.dataset.projectContent] || '';
            }
        });
        root.querySelectorAll('[data-project-language]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.projectLanguage === language)));
        root.querySelectorAll('[data-project-files]').forEach(panel => { panel.hidden = panel.dataset.projectFiles !== language || !matchesVariant(panel); });
        root.querySelectorAll('[data-project-helper]').forEach(panel => { panel.hidden = panel.dataset.projectHelper !== language || file !== (panel.dataset.projectHelperFor || 'main'); });
        root.querySelectorAll('[data-project-open]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.projectOpen === file)));
    };
    root.querySelectorAll('[data-project-language]').forEach(button => button.addEventListener('click', () => { language = button.dataset.projectLanguage; render(); }));
    root.querySelectorAll('[data-project-open]').forEach(button => button.addEventListener('click', () => { file = button.dataset.projectOpen; render(); }));
    root.addEventListener('project-variant', event => { root.dataset.projectVariant = event.detail; file = 'main'; render(); });
    render();
}

for (const root of document.querySelectorAll('[data-structure-examples]')) {
    const buttons = [...root.querySelectorAll('[data-structure-select]')];
    const select = button => {
        const type = button.dataset.structureSelect;
        buttons.forEach(other => other.setAttribute('aria-pressed', String(other === button)));
        root.querySelectorAll('[data-structure-description]').forEach(panel => { panel.hidden = panel.dataset.structureDescription !== type; });
        root.querySelectorAll('[data-project-editor]').forEach(editor => editor.dispatchEvent(new CustomEvent('project-variant', {detail: type})));
    };
    buttons.forEach((button, index) => {
        button.addEventListener('click', () => select(button));
        button.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
            select(buttons[next]); buttons[next].focus();
        });
    });
}

// Publishing demo: language tabs, and a Formatted / Raw output toggle for the model preview
for (const root of document.querySelectorAll('[data-publish-demo]')) {
    const tabs = [...root.querySelectorAll('[data-pd-tab]')];
    let language = tabs[0]?.dataset.pdTab, view = 'formatted';
    const render = () => {
        tabs.forEach(tab => { const active = tab.dataset.pdTab === language; tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1; });
        root.querySelectorAll('[data-pd-view]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.pdView === view)));
        root.querySelectorAll('[data-pd-lang]').forEach(part => {
            part.hidden = part.dataset.pdLang !== language || (part.dataset.pdPane !== undefined && part.dataset.pdPane !== view);
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => { language = tab.dataset.pdTab; render(); });
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = tabs[event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];
            language = next.dataset.pdTab; render(); next.focus();
        });
    });
    root.querySelectorAll('[data-pd-view]').forEach(button => button.addEventListener('click', () => { view = button.dataset.pdView; render(); }));
}

for (const root of document.querySelectorAll('[data-cloud-screens]')) {
    const tabs = [...root.querySelectorAll('[data-cloud-tab]')];
    let pane = root.querySelector('[data-cloud-screen]:not([hidden])'), slide = 0, timer = null, visible = false, paused = false, stopped = reducedMotion.matches;
    // Fetch the screenshots for the current theme once the section is near, so switching shows no blank pane.
    new IntersectionObserver(entries => {
        visible = entries.some(entry => entry.isIntersecting);
        if (visible) {
            const theme = document.documentElement.classList.contains('dark') ? '-dark.' : '-light.';
            root.querySelectorAll('img[loading="lazy"]').forEach(img => { if (img.src.includes(theme)) img.loading = 'eager'; });
        }
        play();
    }, { rootMargin: '200px' }).observe(root);
    // Panes with several screenshots advance on their own while the section is in view.
    const show = index => {
        const slides = [...pane.querySelectorAll('[data-cloud-slide]')];
        slide = (index + slides.length) % slides.length;
        slides.forEach((el, i) => { el.hidden = i !== slide; });
        pane.querySelectorAll('[data-cloud-caption]').forEach((el, i) => { el.hidden = i !== slide; });
        pane.querySelectorAll('[data-cloud-dot]').forEach((dot, i) => dot.setAttribute('aria-pressed', String(i === slide)));
    };
    const play = () => {
        clearInterval(timer);
        timer = null;
        if (!visible || paused || stopped || pane.querySelectorAll('[data-cloud-slide]').length < 2) return;
        timer = setInterval(() => show(slide + 1), 5500);
    };
    const stage = pane.parentElement;
    stage.addEventListener('pointerenter', () => { paused = true; play(); });
    stage.addEventListener('pointerleave', () => { paused = false; play(); });
    root.querySelectorAll('[data-cloud-dot]').forEach(dot => dot.addEventListener('click', () => { show(Number(dot.dataset.cloudDot)); play(); }));
    // Pause stops the slides until the reader presses Play; it applies to every pane.
    const pauses = [...root.querySelectorAll('[data-cloud-pause]')];
    const label = () => pauses.forEach(button => { button.setAttribute('aria-label', stopped ? 'Play screenshots' : 'Pause screenshots'); button.setAttribute('aria-pressed', String(stopped)); });
    pauses.forEach(button => button.addEventListener('click', () => { stopped = !stopped; label(); play(); }));
    label();
    root.querySelectorAll('[data-cloud-step]').forEach(step => step.addEventListener('click', () => { show(slide + Number(step.dataset.cloudStep)); play(); }));
    const select = tab => {
        tabs.forEach(other => {
            const active = other === tab;
            other.setAttribute('aria-selected', String(active));
            other.tabIndex = active ? 0 : -1;
            const screen = root.querySelector(`[data-cloud-screen="${other.dataset.cloudTab}"]`);
            screen.hidden = !active;
            if (active) pane = screen;
        });
        show(0);
        play();
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(tab));
        tab.addEventListener('keydown', event => {
            const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
            if (!step) return;
            event.preventDefault();
            const next = tabs[(index + step + tabs.length) % tabs.length];
            select(next);
            next.focus();
        });
    });
}
