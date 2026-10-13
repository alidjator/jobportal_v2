# Phase 8: console management of admin roles and accounts

Found on 2026-10-13 while listing the admin features: admin accounts and roles are console-only (`frontend.md` 9.3), but the console has no tool to
create a role other than `super_admin`, set a role's permissions, change an admin's role or suspend an admin. Every admin would have to be
`super_admin`, and the permission-based admin menu would never apply. Coded before go-live, ahead of the other phase 8 work.

## Decisions

- 2026-10-13: complete the console commands instead of adding admin-management pages; the panel stays without them (decision gate, decided by the
  user).
- 2026-10-13: the commands below agreed as proposed, coded now, before go-live (not with the rest of phase 8) ([detail](#design)).

## Current state (read from the code, 2026-10-13)

- Commands: `identity:sync-permissions` (creates the catalog's permissions, gives `super_admin` all of them), `identity:create-admin --role=`
  (one role, which must exist), `identity:reset-admin-two-factor`.
- Spatie tables exist for roles, role permissions, admin roles and direct admin permissions (`identity_admin_permissions`); only `super_admin` is
  ever created (`AdminRoles::SUPER_ADMIN`).
- `identity_admins.status` uses `AccountStatus` and sign-in already refuses a suspended admin; nothing sets it.
- The audit trail has the actor type `system` for work done outside a request.

## Design

Agreed by the user on 2026-10-13.

| Command | Does |
|---|---|
| `identity:list-permissions` | Every permission of the catalog with its group |
| `identity:list-roles` | Every role with its permissions and the number of admins holding it |
| `identity:create-role {name} {--permission=*}` | A new role (`^[a-z][a-z0-9_]{2,49}$`) with the given permissions; unknown permissions are refused |
| `identity:set-role-permissions {name} {--permission=*}` | Replaces the role's permissions (asks for confirmation and shows what is added and removed) |
| `identity:delete-role {name}` | Refused while an admin holds the role |
| `identity:list-admins` | Admins with role, status, two-factor and last sign-in |
| `identity:set-admin-role {email} {role}` | Replaces the admin's role (one role per admin) |
| `identity:suspend-admin {email}` / `identity:reactivate-admin {email}` | Suspension revokes every token of the admin at once |
| `identity:reset-admin-password {email}` | New password from a hidden prompt, every token revoked |

Rules:

- **Roles only:** permissions are given through roles; the direct admin permission table stays unused, so one place answers "who can do what".
- **`super_admin` is protected:** it cannot be deleted or edited (`sync-permissions` keeps it complete), and the last active `super_admin` cannot be
  suspended or moved to another role, so nobody locks the platform out.
- **Effect:** at once; the permission cache is cleared after each change and every request checks permissions again.
- **Audit:** each change is an audit entry with actor type `system`, the operating-system user who ran the command, and the before and after values
  (new actions `admin.role_created`, `admin.role_permissions_changed`, `admin.role_deleted`, `admin.role_changed`, `admin.suspended`,
  `admin.reactivated`, `admin.password_reset`).
- **Commands run as `jobportal`**, never root, like the other artisan commands; the deployment checklist lists the roles to create before go-live.

## Coded (2026-10-13)

- **Application** (`src/Identity/Application/Admin`): `CreateAdminRole`, `ChangeAdminRolePermissions` (`plan()` for the confirmation, then
  `execute()`), `DeleteAdminRole`, `ChangeAdminRole`, `SuspendAdmin`, `ReactivateAdmin`, `ResetAdminPassword`, the queries `ListAdminPermissions`,
  `ListAdminRoles`, `ListAdmins`; `AdminRolePermissions` checks names against the catalog and creates missing catalog permissions before a role uses
  them; `LastSuperAdminGuard`; the port `AdminDirectory` (Spatie reader `SpatieAdminDirectory`); `AdminRbacWriter` gained `replaceRole` and
  `deleteRole`. `Admin` gained `suspend()` and `reactivate()`.
- **Commands** (`src/Identity/Interface/Console`, trait `NamesTheOperator` reads `SUDO_USER`, then `USER`, then `LOGNAME`): the ten in the table above.
  Every command that changes something asks for confirmation, except `create-role` and `reactivate-admin`; the password is read from a hidden prompt.
- **Errors:** `invalid-role-name`, `permissions-required`, `unknown-permission` (422); `role-already-exists`, `role-protected`, `role-in-use`,
  `role-unchanged`, `last-super-admin`, `admin-not-active`, `admin-not-suspended` (409). The commands print the message; the codes reach the error
  catalog only because it scans the source.
- **Audit:** seven events in `src/Identity/Domain/Events` recorded by `RecordIdentityAuditEntries` with actor type `system` and `operator` in the
  metadata; role entries have subject type `admin_role` and the role name in the metadata (no subject id), administrator entries subject type `admin`.
- **Interpretations for the user's review:** (1) an administrator holds exactly one role, and `set-admin-role` replaces it; (2) a role needs at least
  one permission; (3) a suspended `super_admin` does not count toward the "last active super_admin" guard, so the last active one can be neither
  suspended nor moved; (4) resetting a password keeps the second factor (losing the device is `identity:reset-admin-two-factor`); (5) role changes take
  effect on the next request, tokens are kept; suspension and password reset revoke every token.
- **Tests:** `tests/Feature/Identity/AdminConsoleTest.php`.
