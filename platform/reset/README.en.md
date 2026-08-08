# Reset

Applies the shared WordPress baseline policy used by SoinProduction themes: protocol allow-list extensions, XML-RPC/editor/comment cleanup, default-content handling, dashboard/menu cleanup, update policy and theme defaults.

Enable it with `reset`. This is a broad policy module, not a small helper; review `index.php` before enabling it in an existing project. Activation helpers prefixed `sp_reset_` can create/restore the Home page and remove untouched default content.
