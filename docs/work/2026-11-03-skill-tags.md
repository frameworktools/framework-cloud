# Skill tags and Stata

The skills catalog now has curated tags, search, source filters, and labeled tables. Selecting several tags requires every selected tag. Skill names open the reader. Row actions use ghost buttons, with outline icons on phones.

The initial taxonomy contains Framework, Visualization, Modeling, AI Prose, Notebooks, Reading data, Packages, Outputs, Publishing, Privacy, Exploration, and Publication. An administrator can add and rename tags at `/admin/skill-tags`. Other accounts can assign existing tags to their skills. Stable tag keys preserve links and assignments after a rename.

Tags belong to skills as catalog metadata. They do not alter the versioned skill bundle or its hash. Forks inherit their source skill's current tags. The model hook covers web and sync forks. Existing canonical skills and forks receive tags in the migration. Reseeding preserves curated names and assignments.

Stata is available in the skill catalog, reader, editor, and skill sets. New skill forms use the account's saved environment. Catalog tabs start from that preference on each visit, including visits with a stale language query. Detail links still request the selected language explicitly. Package sets retain their supported R and Python environments.

## Validation

- 49 focused PHP tests passed with 692 assertions. Coverage includes taxonomy permissions, fork inheritance through sync, Stata web edits and sync updates, and saved environment defaults.
- All 9 JavaScript tests passed. Filters combine language, source, search, and every selected tag.
- `npm run build` passed.
- Chrome checks passed in light mode, dark mode, and at 390px. Screenshots were inspected for the catalog, reader, editor, and admin tag page.
- A real Stata skill was created, tagged, read, revised, and deleted through the browser. Admin tag creation and renaming were tested through the browser. Temporary records were removed.

The migration and frontend build are applied locally. No release, commit, push, or production deployment was made for this change. The unrelated `polyscope.json` edit was preserved.

## Preview

`tunnels` maps this checkout to https://framework-cloud.pequod.dev. The skills page is https://framework-cloud.pequod.dev/skills. The origin was tested in Chrome. Public routing reached Cloudflare Access; authenticated public rendering was not verified. Shared preview snapshots timed out twice, so visual checks used installed Chrome.
