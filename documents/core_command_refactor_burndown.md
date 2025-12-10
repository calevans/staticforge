# Core Command Refactoring Burndown List

**Goal**: Transition StaticForge to a Microkernel Architecture by converting hardcoded Core Commands into modular Features.

## Principles
- **KISS**
- **YAGNI**
- **DRY**
- **SOLID**

## Strategy
- Move commands from `src/Commands/` to dedicated Feature directories in `src/Features/`.
- Each new Feature will implement `FeatureInterface` and register its commands via the `CONSOLE_INIT` event.
- `bin/staticforge.php` will be cleaned up to only load `site:init` and the `FeatureManager`.

## Feature Groupings

### 1. FeatureTools (Priority: High)
*Encapsulates tooling for managing features.*
- [x] Create `src/Features/FeatureTools/` structure.
- [x] Move `FeatureCreateCommand` -> `src/Features/FeatureTools/Commands/FeatureCreateCommand.php`.
- [x] Move `FeatureSetupCommand` -> `src/Features/FeatureTools/Commands/FeatureSetupCommand.php`.
- [x] Move `ListFeaturesCommand` -> `src/Features/FeatureTools/Commands/ListFeaturesCommand.php`.
- [x] Create `FeatureTools/Feature.php` and register commands in `CONSOLE_INIT`.

### 2. SiteBuilder (Priority: Medium)
*Encapsulates the core build process.*
- [x] Create `src/Features/SiteBuilder/` structure.
- [x] Move `RenderSiteCommand` -> `src/Features/SiteBuilder/Commands/RenderSiteCommand.php`.
- [x] Create `SiteBuilder/Feature.php` and register command.

### 3. Deployment (Priority: Medium)
*Encapsulates deployment logic.*
- [x] Create `src/Features/Deployment/` structure.
- [x] Move `UploadSiteCommand` -> `src/Features/Deployment/Commands/UploadSiteCommand.php`.
- [x] Create `Deployment/Feature.php` and register command.

### 4. DevServer (Priority: Low)
*Encapsulates the local development server.*
- [x] Create `src/Features/DevServer/` structure.
- [x] Move `DevServerCommand` -> `src/Features/DevServer/Commands/DevServerCommand.php`.
- [x] Create `DevServer/Feature.php` and register command.

### 5. Cleanup & Wiring
- [x] Update `bin/staticforge.php`:
    - [x] Remove manual `add()` calls for moved commands.
    - [x] Ensure `site:init` remains.
- [x] Verify all commands appear in `bin/staticforge.php list`.
- [x] Verify `disabled_features` in `siteconfig.yaml` can successfully disable these new features.
