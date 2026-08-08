# SP Login Branding

Replaces the WordPress login logo with `BUILD . 'img/logo.svg'`. Enable it with the `sp-login-branding` platform module.

The host theme must define `BUILD` before PHP Kit platform modules load. Styling is injected only on the login screen through `login_head`.
