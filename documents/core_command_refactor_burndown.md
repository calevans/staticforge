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
- [ ] Create `src/Features/FeatureTools/` structure.
- [ ] Move `FeatureCreateCommand` -> `src/Features/FeatureTools/Commands/FeatureCreateCommand.php`.
- [ ] Move `FeatureSetupCommand` -> `src/Features/FeatureTools/Commands/FeatureSetupCommand.php`.
- [ ] Move `ListFeaturesCommand` -> `src/Features/FeatureTools/Commands/ListFeaturesCommand.php`.
- [ ] Create `FeatureTools/Feature.php` and register commands in `CONSOLE_INIT`.

### 2. SiteBuilder (Priority: Medium)
*Encapsulates the core build process.*
- [ ] Create `src/Features/SiteBuilder/` structure.
- [ ] Move `RenderSiteCommand` -> `src/Features/SiteBuilder/Commands/RenderSiteCommand.php`.
- [ ] Create `SiteBuilder/Feature.php` and register command.

### 3. Deployment (Priority: Medium)
*Encapsulates deployment logic.*
- [ ] Create `src/Features/Deployment/` structure.
- [ ] Move `UploadSiteCommand` -> `src/Features/Deployment/Commands/UploadSiteCommand.php`.
- [ ] Create `Deployment/Feature.php` and register command.

### 4. DevServer (Priority: Low)
*Encapsulates the local development server.*
- [ ] Create `src/Features/DevServer/` structure.
- [ ] Move `DevServerCommand` -> `src/Features/DevServer/Commands/DevServerCommand.php`.
- [ ] Create `DevServer/Feature.php` and register command.

### 5. Cleanup & Wiring
- [ ] Update `bin/staticforge.php`:
    - [ ] Remove manual `add()` calls for moved commands.
    - [ ] Ensure `site:init` remains.
- [ ] Verify all commands appear in `bin/staticforge.php list`.
- [ ] Verify `disabled_features` in `siteconfig.yaml` can successfully disable these new features.
