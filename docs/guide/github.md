# GitHub integration

GitHub integration lets you sign in with GitHub and create projects as GitHub
repositories. It is free on framework.pub. On your own server, an admin sets
it up first (see [Server setup](server-setup.md#github)).

## Connect your account

1. Open **Settings > GitHub** and connect your GitHub account. If you signed
   up with GitHub, it is already connected.
2. Install the Framework app on your GitHub account and on any organization
   where you want new repositories. GitHub asks which repositories the app may
   reach. The app can only work in repositories it creates or that you give
   it.

## Create a project on GitHub

1. Open **Projects > New project** and choose a blueprint.
2. Under **Where to set it up**, choose **On GitHub**.
3. Pick the account or organization, name the repository, and choose whether
   it is private. Blueprints for restricted data require a private
   repository.
4. Click **Create on GitHub**.

Framework Cloud creates the repository, and a one-time GitHub Action builds
the project files in it. The project page shows when it is done. Then clone
the repository and start working:

```sh
git clone https://github.com/you/your-project.git
```

## Disconnecting

**Settings > GitHub > Disconnect** removes the connection. Your repositories
stay on GitHub. To remove the app's access completely, uninstall it in your
GitHub settings under **Applications**.
