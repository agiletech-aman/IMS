# TODO - Users -> Faculty + Import functionality

## Steps
- [x] 1. Create `resources/views/partials/user-import-modal.blade.php` (mirror asset import modal)
- [x] 2. Edit `resources/views/users/index.blade.php`:
  - [x] a. Replace user-facing "User"/"user" text with "Faculty"/"faculty"
  - [x] b. Add Import (and Export) button gated by `@permission('users','import')`
  - [x] c. Render the user import modal partial
  - [x] d. Add session-flag auto-open script for the import modal
- [x] 3. Remove `Status` and `Login Enabled` from import sample (defaults: Active + login enabled)
- [x] 4. Default role = role of the current module/page (from hidden `role` field)
- [x] 5. Verify the updated blade files
