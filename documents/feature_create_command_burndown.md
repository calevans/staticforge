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
- [x] Create `src/Commands/FeatureCreateCommand.php` extending `Symfony\Component\Console\Command\Command`.
- [x] Register the command in `bin/staticforge.php`.

### 2. Implementation Logic
- [x] Implement `configure()` to accept the `name` argument.
- [x] Implement `execute()` validation:
    - [x] Ensure feature name is valid (PascalCase, alphanumeric).
    - [x] Check if feature directory already exists in `src/Features/`.
- [x] Implement Directory Creation:
    - [x] Create `src/Features/<FeatureName>/`.
    - [x] Create `src/Features/<FeatureName>/Services/`.

### 3. Code Generation (Templates)
- [x] Generate `Feature.php`:
    - [x] Namespace: `EICC\StaticForge\Features\<FeatureName>`
    - [x] Class `Feature` extends `BaseFeature` implements `FeatureInterface`.
    - [x] `register()` method that instantiates `<FeatureName>Service` with Logger/Container.
    - [x] `eventListeners` array with a sample `PRE_LOOP` or `POST_RENDER` hook.
- [x] Generate `Services/<FeatureName>Service.php`:
    - [x] Namespace: `EICC\StaticForge\Features\<FeatureName>\Services`
    - [x] Constructor accepting `Log` (and optionally `Container` or `Environment`).
    - [x] Sample method corresponding to the event listener in `Feature.php`.

### 4. Verification
- [x] Run `bin/staticforge.php list` to verify command presence.
- [x] Run `bin/staticforge.php feature:create TestFeature` and verify file structure and content.
