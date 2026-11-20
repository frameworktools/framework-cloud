# GitHub integration

GitHub integration enables GitHub sign-in and creates scaffolded project repositories on GitHub. Framework Public Cloud includes GitHub integration. Self-hosted servers require a GitHub App configured by an administrator in [Server setup](server-setup.md#github).

## Account connection

1. Open **Account > GitHub connection** and connect your GitHub account. GitHub sign-in does not connect your account for repositories.
2. Install the Framework GitHub App on your personal account and on any organization where you plan to create repositories. GitHub prompts you to choose repository access. Framework operates only on repositories it creates or repositories you explicitly grant.

## GitHub projects

1. Open **Projects > New project**, choose a language and blueprint, and continue to **Review**.
2. Choose **Destination > GitHub repository**.
3. Select the owner account or organization, enter the repository name, and set repository visibility. Sensitive-data blueprints require a private repository.
4. Select **Create on GitHub**.

Framework Cloud creates the repository and triggers a one-time GitHub Action to generate the project files. The workflow runs the `framework` R package for R projects, `framework-py` for Python projects, and `fw` for Stata projects. The server pins each tool with `GITHUB_FRAMEWORK_SHA`, `GITHUB_FRAMEWORK_PY_SHA`, and `GITHUB_FW_VERSION`, which default to tested versions. An empty or invalid `GITHUB_FRAMEWORK_SHA` turns repository creation off. An empty or invalid `GITHUB_FRAMEWORK_PY_SHA` or `GITHUB_FW_VERSION` turns it off for Python or Stata. In those cases **GitHub repository** appears disabled with the reason. When the run succeeds, Framework Cloud replaces the layers, workflow, and scaffold commits with one **Scaffold with Framework** commit, so the history holds only that commit above GitHub's initial commit. A commit pushed during the run keeps the history as it is, and the workflow is removed in a commit of its own.

When the project page reports completion, clone the repository:

```sh
git clone https://github.com/you/your-project.git
```

The project token stays out of the repository. Before `fw pull` or `fw publish` in the clone, link it to the project:

```sh
fw project link --from your-project
```

## Repository updates

A project's **GitHub & Pages** tab shows the repository and its status. When the project setup changes after the repository was created, select **Update on GitHub**. Framework Cloud rebuilds its own files from the current setup and opens an **Update Framework setup** pull request on a `framework/update-*` branch. Only `.framework/spec.json`, `settings.yml`, `settings/*.yml`, and installed skills are considered. Notebooks, scripts, data, and README files stay unchanged. Files edited since Framework's last commit, or matched by `.gitignore`, are left alone and listed in the pull request. Updates never push to the default branch. Package changes through the API or the Claude connector also stay on Framework Cloud until an update. `POST /api/v1/projects/{slug}/github/update` and the connector's `projects_github_update` tool request one. Updating requires a connected GitHub account, the Framework app installed on the repository owner, and the server's `scaffolder` service.

## GitHub Pages

Under **GitHub Pages** on the same tab, select **Publish with GitHub Pages** to turn on Pages and commit `.github/workflows/framework-pages.yml`. The workflow deploys the repository's `published/` folder on each push to the default branch that changes it. The site is public even when the repository is private. Pages on a private repository requires a paid GitHub plan. Select **Turn off** to disable Pages and remove the workflow. The `published/` folder stays in the repository.

## Disconnection

Open **Account > GitHub connection** and select **Disconnect** to unlink your GitHub account from Framework Cloud. Existing repositories remain on GitHub. To revoke repository permissions altogether, uninstall the Framework app in GitHub under **Settings > Applications**.
