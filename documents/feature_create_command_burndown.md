# Feature Create Command Burndown List

**Goal**: Implement `feature:create` to scaffold new internal features following the CategoryIndex Gold Standard.

## Specifications
- **Command**: `feature:create <FeatureName>`
- **Target Location**: `src/Features/<FeatureName>/`
- **Architecture**:
    - `Feature.php`: Thin controller, registers service, defines event listeners.
    - `Services/<FeatureName>Service.php`: Business logic class.
- **Exclusions**: No unit tests, documentation, or config files (these are handled during extraction).

## Tasks

### 1. Command Structure
- [ ] Create `src/Commands/FeatureCreateCommand.php` extending `Symfony\Component\Console\Command\Command`.
- [ ] Register the command in `bin/staticforge.php`.

### 2. Implementation Logic
- [ ] Implement `configure()` to accept the `name` argument.
- [ ] Implement `execute()` validation:
    - [ ] Ensure feature name is valid (PascalCase, alphanumeric).
    - [ ] Check if feature directory already exists in `src/Features/`.
- [ ] Implement Directory Creation:
    - [ ] Create `src/Features/<FeatureName>/`.
    - [ ] Create `src/Features/<FeatureName>/Services/`.

### 3. Code Generation (Templates)
- [ ] Generate `Feature.php`:
    - [ ] Namespace: `EICC\StaticForge\Features\<FeatureName>`
    - [ ] Class `Feature` extends `BaseFeature` implements `FeatureInterface`.
    - [ ] `register()` method that instantiates `<FeatureName>Service` with Logger/Container.
    - [ ] `eventListeners` array with a sample `PRE_LOOP` or `POST_RENDER` hook.
- [ ] Generate `Services/<FeatureName>Service.php`:
    - [ ] Namespace: `EICC\StaticForge\Features\<FeatureName>\Services`
    - [ ] Constructor accepting `Log` (and optionally `Container` or `Environment`).
    - [ ] Sample method corresponding to the event listener in `Feature.php`.

### 4. Verification
- [ ] Run `bin/staticforge.php list` to verify command presence.
- [ ] Run `bin/staticforge.php feature:create TestFeature` and verify file structure and content.
