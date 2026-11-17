// Hidden panels arrive as <template data-defer> (App\Support\DeferHidden). A panel's
// contents join the page the first time it is shown, so click handling is delegated.
// Widgets inside a deferred panel are set up when it is revealed (see onReveal).
const revealHooks = [];
const onReveal = hook => revealHooks.push(hook);
const reveal = el => {
    const template = el.querySelector(':scope > template[data-defer]');
    if (!template) return;
    template.replaceWith(template.content);
    revealHooks.forEach(hook => hook(el));
};
const setHidden = (el, hidden) => { if (!hidden) reveal(el); el.hidden = hidden; };
const onClick = (root, selector, handler) => root.addEventListener('click', event => {
    const target = event.target.closest(selector);
    if (target && root.contains(target)) handler(target, event);
});
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
// The headline's last line: the opener, then the applied fields in a fresh random order, then the
// opener again. It pauses with the code examples' pause button, while hovered, and while unseen.
for (const root of document.querySelectorAll('[data-hero-words]')) {
    if (reducedMotion.matches) break;
    const words = [...root.querySelectorAll('[data-hero-word]')];
    const pass = () => [...words.keys()].slice(1).sort(() => Math.random() - 0.5);
    const place = (word, state) => {
        word.classList.toggle('opacity-0', state !== 'in');
        word.classList.toggle('translate-y-full', state === 'below');
        word.classList.toggle('-translate-y-full', state === 'above');
    };
    let current = 0, queue = [], paused = false, visible = true;
    document.addEventListener('landing-motion', event => { paused = event.detail.paused; });
    new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; }).observe(root);
    reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) paused = true; });
    setInterval(() => {
        if (paused || !visible || document.hidden || root.parentElement.matches(':hover')) return;
        if (!queue.length) queue = current === 0 ? pass() : [0];
        const next = queue.shift();
        place(words[current], 'above');
        // Start the incoming word below without a transition, then slide it up
        words[next].style.transition = 'none';
        place(words[next], 'below');
        words[next].offsetHeight;
        words[next].style.transition = '';
        place(words[next], 'in');
        current = next;
    }, 3500);
}

for (const root of document.querySelectorAll('[data-landing-demo]')) {
    const slides = [...root.querySelectorAll('[data-demo-slide]')];
    const languageTabs = [...root.querySelectorAll('[data-demo-language]')];
    const pause = root.querySelector('[data-demo-pause]');
    const status = root.querySelector('[data-demo-status]');
    let current = 0, language = 'r', stopped = reducedMotion.matches || window.innerWidth < 768;
    const show = index => {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            setHidden(slide, i !== current);
            slide.querySelectorAll('[data-demo-code]').forEach(code => { setHidden(code, code.dataset.demoCode !== language); });
        });
        root.querySelectorAll('[data-demo-select]').forEach(button => button.setAttribute('aria-pressed', String(Number(button.dataset.demoSelect) === current)));
        root.querySelector('[data-demo-title]').textContent = root.querySelector('[data-demo-view="result"]').getAttribute('aria-pressed') === 'true' ? 'A consistent structure for every project' : slides[current].getAttribute('aria-label');
        status.textContent = `${slides[current].getAttribute('aria-label')} example`;
    };
    onClick(root, '[data-demo-view]', button => {
        stop();
        if (button.dataset.demoView === 'result') root.querySelector('[data-demo-title]').textContent = 'A consistent structure for every project';
        else root.querySelector('[data-demo-title]').textContent = slides[current].getAttribute('aria-label');
        root.querySelectorAll('[data-demo-view]').forEach(other => other.setAttribute('aria-pressed', String(other === button)));
        root.querySelectorAll('[data-demo-view-panel]').forEach(panel => { setHidden(panel, panel.dataset.demoViewPanel !== button.dataset.demoView); });
        // The project files open in the language the code tabs show
        root.querySelector(`[data-project-language="${language === 'python' ? 'py' : language}"]`)?.click();
    });
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
    onClick(root, '[data-demo-select]', button => { stop(); show(Number(button.dataset.demoSelect)); });
    root.querySelector('[data-demo-previous]').addEventListener('click', () => { stop(); show(current - 1); });
    root.querySelector('[data-demo-next]').addEventListener('click', () => { stop(); show(current + 1); });
    pause.addEventListener('click', () => {
        stopped = !stopped; updatePause();
        // The headline's words pause and play with the examples
        document.dispatchEvent(new CustomEvent('landing-motion', { detail: { paused: stopped } }));
    });
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
        root.querySelectorAll('[data-setup-panel]').forEach(panel => { setHidden(panel, panel.dataset.setupPanel !== key); });
        root.querySelectorAll('[data-setup-path]').forEach(path => { setHidden(path, path.dataset.setupPath !== key); });
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

// Best practices: a topic list, and a language choice that carries across topics
for (const root of document.querySelectorAll('[data-practices]')) {
    const tabs = [...root.querySelectorAll('[data-practice-tab]')];
    let language = 'r';
    const showLanguage = panel => {
        const codes = [...panel.querySelectorAll('[data-practice-code]')];
        const pick = codes.some(code => code.dataset.practiceCode === language) ? language : codes[0]?.dataset.practiceCode;
        codes.forEach(code => setHidden(code, code.dataset.practiceCode !== pick));
        panel.querySelectorAll('[data-practice-language]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.practiceLanguage === pick)));
    };
    const select = tab => {
        tabs.forEach(other => { const active = other === tab; other.setAttribute('aria-selected', String(active)); other.tabIndex = active ? 0 : -1; });
        root.querySelectorAll('[data-practice-panel]').forEach(panel => {
            setHidden(panel, panel.dataset.practicePanel !== tab.dataset.practiceTab);
            if (panel.dataset.practicePanel === tab.dataset.practiceTab) showLanguage(panel);
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(tab));
        tab.addEventListener('keydown', event => {
            if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const step = ['ArrowDown', 'ArrowRight'].includes(event.key) ? 1 : -1;
            const next = tabs[event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + step + tabs.length) % tabs.length];
            select(next); next.focus();
        });
    });
    onClick(root, '[data-practice-language]', button => {
        language = button.dataset.practiceLanguage;
        showLanguage(button.closest('[data-practice-panel]'));
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
const initEditor = root => {
    if (root.dataset.editorReady) return;
    root.dataset.editorReady = 'true';
    let language = root.querySelector('[data-project-language]').dataset.projectLanguage;
    let file = 'main';
    const render = () => {
        const matchesVariant = panel => !root.dataset.projectVariant || panel.dataset.projectVariant === root.dataset.projectVariant;
        const available = [...root.querySelectorAll('[data-project-source]')].filter(panel => panel.dataset.projectSourceLanguage === language && matchesVariant(panel));
        if (!available.some(panel => panel.dataset.projectSource === file)) file = 'main';
        root.querySelectorAll('[data-project-source]').forEach(panel => {
            const active = panel.dataset.projectSourceLanguage === language && panel.dataset.projectSource === file && matchesVariant(panel);
            setHidden(panel, !active);
            if (active) {
                root.querySelector('[data-project-path]').textContent = panel.dataset.projectSourcePath;
                const terminal = root.querySelector('[data-project-terminal]');
                if (terminal) setHidden(terminal, !/console|^Terminal$/i.test(panel.dataset.projectSourcePath));
                const code = panel.querySelector('code');
                if (panel.dataset.projectContent && !code.childElementCount) code.innerHTML = projectSources[panel.dataset.projectContent] || '';
            }
        });
        root.querySelectorAll('[data-project-language]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.projectLanguage === language)));
        root.querySelectorAll('[data-project-files]').forEach(panel => { setHidden(panel, panel.dataset.projectFiles !== language || !matchesVariant(panel)); });
        root.querySelectorAll('[data-project-helper]').forEach(panel => { setHidden(panel, panel.dataset.projectHelper !== language || file !== (panel.dataset.projectHelperFor || 'main')); });
        root.querySelectorAll('[data-project-open]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.projectOpen === file)));
    };
    onClick(root, '[data-project-language]', button => { language = button.dataset.projectLanguage; render(); });
    onClick(root, '[data-project-open]', button => { file = button.dataset.projectOpen; render(); });
    root.addEventListener('project-variant', event => { root.dataset.projectVariant = event.detail; file = 'main'; render(); });
    render();
};
document.querySelectorAll('[data-project-editor]').forEach(initEditor);
onReveal(el => el.querySelectorAll('[data-project-editor]').forEach(initEditor));

for (const root of document.querySelectorAll('[data-structure-examples]')) {
    const buttons = [...root.querySelectorAll('[data-structure-select]')];
    const select = button => {
        const type = button.dataset.structureSelect;
        buttons.forEach(other => other.setAttribute('aria-pressed', String(other === button)));
        root.querySelectorAll('[data-structure-description]').forEach(panel => { setHidden(panel, panel.dataset.structureDescription !== type); });
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
            setHidden(part, part.dataset.pdLang !== language || (part.dataset.pdPane !== undefined && part.dataset.pdPane !== view));
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
    onClick(root, '[data-pd-view]', button => { view = button.dataset.pdView; render(); });
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
        slides.forEach((el, i) => { setHidden(el, i !== slide); });
        pane.querySelectorAll('[data-cloud-caption]').forEach((el, i) => { setHidden(el, i !== slide); });
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
    onClick(root, '[data-cloud-dot]', dot => { show(Number(dot.dataset.cloudDot)); play(); });
    // Pause stops the slides until the reader presses Play; it applies to every pane.
    const label = () => root.querySelectorAll('[data-cloud-pause]').forEach(button => { button.setAttribute('aria-label', stopped ? 'Play screenshots' : 'Pause screenshots'); button.setAttribute('aria-pressed', String(stopped)); });
    onClick(root, '[data-cloud-pause]', () => { stopped = !stopped; label(); play(); });
    label();
    onClick(root, '[data-cloud-step]', step => { show(slide + Number(step.dataset.cloudStep)); play(); });
    const select = tab => {
        tabs.forEach(other => {
            const active = other === tab;
            other.setAttribute('aria-selected', String(active));
            other.tabIndex = active ? 0 : -1;
            const screen = root.querySelector(`[data-cloud-screen="${other.dataset.cloudTab}"]`);
            setHidden(screen, !active);
            if (active) pane = screen;
        });
        show(0);
        label();
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

// Step 04: a tab per host. Each host plays its scenes in a loop: the insert step in its editor,
// the link or image going in, and the published post. Panels and scenes share grid cells, so
// nothing changes height. The loop waits while the step is off screen or hovered, and stops
// for good when a step button is used or motion is reduced; the post stays shown then.
for (const root of document.querySelectorAll('[data-share-demo]')) {
    const tabs = [...root.querySelectorAll('[data-share-tab]')];
    const durations = [1800, 1800, 3200];
    let host = tabs[0].dataset.shareTab, scene = 2, playing = !reducedMotion.matches, visible = false, hovered = false, timers = [];
    const later = (fn, ms) => timers.push(setTimeout(fn, ms));
    const panel = () => root.querySelector(`[data-share-panel="${host}"]`);
    const play = element => {
        if (element.dataset.type !== undefined) {
            const text = element.dataset.type;
            element.textContent = '';
            [...text].forEach((_, i) => later(() => { element.textContent = text.slice(0, i + 1); }, 300 + i * 70));
        }
        // Links and code arrive whole, as a paste, with a moment's highlight
        if (element.dataset.paste !== undefined) {
            element.dataset.original ??= element.textContent;
            element.textContent = '';
            later(() => {
                element.textContent = element.dataset.original;
                element.classList.add('bg-sky-400/25');
                later(() => element.classList.remove('bg-sky-400/25'), 500);
            }, 250);
        }
        if (element.dataset.swap !== undefined) {
            element.dataset.original ??= element.textContent;
            element.textContent = element.dataset.original;
            later(() => { element.textContent = element.dataset.swap; }, 800);
        }
    };
    const show = index => {
        timers.forEach(clearTimeout); timers = [];
        scene = index;
        panel().querySelectorAll('[data-share-scene]').forEach(el => {
            const active = Number(el.dataset.shareScene) === index;
            el.classList.toggle('opacity-0', !active); el.classList.toggle('opacity-100', active);
            el.inert = !active;
            if (!active) return;
            el.querySelectorAll('[data-type], [data-paste], [data-swap]').forEach(play);
            // A scene taller than the frame scrolls down to its end after a moment
            el.querySelectorAll('[data-scroll]').forEach(page => {
                page.style.transition = 'none';
                page.style.transform = '';
                page.offsetHeight;
                const end = () => `translateY(-${Math.max(0, page.scrollHeight - page.parentElement.clientHeight)}px)`;
                if (reducedMotion.matches || !playing) { page.style.transform = end(); return; }
                later(() => { page.style.transition = 'transform 0.9s ease-in-out'; page.style.transform = end(); }, Number(page.dataset.scroll));
            });
            const reveal = el.querySelector('[data-reveal]');
            if (reveal) {
                reveal.classList.add('opacity-0');
                el.querySelector('[data-hide-on-reveal]').hidden = false;
                later(() => { reveal.classList.remove('opacity-0'); el.querySelector('[data-hide-on-reveal]').hidden = true; }, 800);
            }
        });
        root.querySelectorAll(`[data-share-host="${host}"] [data-share-step]`).forEach(step => step.setAttribute('aria-pressed', String(Number(step.dataset.shareStep) === index)));
        const timing = panel().dataset.shareDurations ? JSON.parse(panel().dataset.shareDurations) : durations;
        if (playing) later(advance, timing[index]);
    };
    const advance = () => {
        if (!playing) return;
        if (!visible || hovered || document.hidden) { later(advance, 600); return; }
        show((scene + 1) % durations.length);
    };
    const stop = () => { playing = false; };
    const select = tab => {
        host = tab.dataset.shareTab;
        tabs.forEach(other => { const active = other === tab; other.setAttribute('aria-selected', String(active)); other.tabIndex = active ? 0 : -1; });
        root.querySelectorAll('[data-share-panel]').forEach(el => {
            const active = el.dataset.sharePanel === host;
            el.classList.toggle('invisible', !active);
            el.inert = !active;
        });
        root.querySelectorAll('[data-share-host]').forEach(el => setHidden(el, el.dataset.shareHost !== host));
        show(playing ? 0 : 2);
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
    onClick(root, '[data-share-step]', step => { stop(); show(Number(step.dataset.shareStep)); });
    root.querySelector('figure').addEventListener('mouseenter', () => { hovered = true; });
    root.querySelector('figure').addEventListener('mouseleave', () => { hovered = false; });
    reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) { stop(); show(2); } });
    document.addEventListener('landing-motion', event => { if (event.detail.paused) { stop(); show(2); } });
    // The loop starts from the first scene once the step comes into view
    let started = false;
    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        if (visible && playing && !started) { started = true; show(0); }
    }, { threshold: 0.4 }).observe(root);
    show(2);
}
