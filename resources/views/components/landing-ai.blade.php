@php
    // Each language opens on its plotting skill (ggplot2, matplotlib, Stata graphs); the other
    // installed skills are listed beside it.
    $aiPanels = [];
    $aiFiles = [];
    foreach (['r' => ['R', 'ggplot2-exploring'], 'py' => ['Python', 'matplotlib-exploring']] as $language => [$label, $plotting]) {
        $project = \App\Support\LandingProject::files($language);
        $main = ".claude/skills/$plotting/SKILL.md";
        $aiPanels[$language] = ['label' => $label, 'path' => $main, 'code' => $project[$main]];
        $aiFiles[$language] = array_intersect_key($project, array_flip(['AGENTS.md', '.claude/skills/framework-data/SKILL.md', '.claude/skills/framework-workflow/SKILL.md', '.claude/skills/framework-outputs/SKILL.md', '.claude/skills/framework-packages/SKILL.md']));
    }
    $stataProject = \App\Support\LandingProject::files('stata');
    $aiPanels['stata'] = ['label' => 'Stata', 'path' => '.agents/skills/stata-graphs/SKILL.md', 'code' => $stataProject['.agents/skills/stata-graphs/SKILL.md']];
    $aiFiles['stata'] = array_intersect_key($stataProject, array_flip(['AGENTS.md', '.agents/skills/framework-stata/SKILL.md', '.agents/skills/framework-sensitive-data/SKILL.md']));
@endphp
<section id="ai" class="border-t border-slate-800/10 py-14 sm:py-20 dark:border-white/10">
    <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight">AI agent skills</h2>
        <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-400">Framework provides skills for R, Python, and Stata, including plotting skills for ggplot2, matplotlib, and Stata graphs. Create your own and share them across projects.</p>
    </div>
    <div class="mt-8 rounded-xl shadow-panel-deep dark:shadow-panel-deep-dark"><x-landing-editor :panels="$aiPanels" :files="$aiFiles" :expand-agents="true" :flat-files="true" max-height="max-h-[22rem]" title="Included agent skills" /></div>
    <div class="mt-4 text-center"><a href="{{ route('skills.index') }}" class="text-sm text-slate-500 underline decoration-slate-300 underline-offset-4">Browse skills &rarr;</a></div>
</section>
