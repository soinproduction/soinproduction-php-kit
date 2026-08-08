# SP Content Admin

Maintains a restricted `content_admin` role based on Administrator capabilities while denying plugin-management operations. It also hides inaccessible admin menu and toolbar entries.

Enable it with `sp-content-admin`. Use `sp_is_content_admin()` when project code needs to identify this role. Capability enforcement remains server-side through `map_meta_cap`; hiding UI is only a secondary measure.
