# Feature: S3 Media Offload Burndown List

## Overview
Allow selective offloading of large media assets (e.g., podcast MP3s) to an S3 bucket. This reduces the repository size and offloads bandwidth. The process is manual and explicit via CLI commands.

## Principles
- **KISS**: Direct file modification. No complex mapping databases.
- **Static**: Files are public read-only. No dynamic signing.
- **Explicit**: User chooses exactly which files to upload.

## Configuration
- [ ] **Add AWS SDK**: Add `aws/aws-sdk-php` to `composer.json`.
- [ ] **Environment Variables**: Update `.env` and `.env.example` with:
    - `AWS_ACCESS_KEY_ID`
    - `AWS_SECRET_ACCESS_KEY`
    - `AWS_DEFAULT_REGION`
    - `AWS_BUCKET`
    - `AWS_URL_PREFIX` (Optional, for custom domains/CDNs)

## Commands

### `media:upload <filepath>`
- [ ] **Validation**: Check if file exists locally.
- [ ] **Upload**: Upload file to S3 bucket (maintaining relative path structure or flat? *Decision: Maintain relative structure from `content/` or `public/` root to avoid collisions*).
- [ ] **Verification**: Perform an HTTP HEAD/GET request to the generated S3 URL to ensure it is publicly accessible.
- [ ] **Refactor Content**:
    - Scan all files in `content/` (recursively).
    - Replace local relative paths (e.g., `/assets/audio/episode1.mp3`) with absolute S3 URLs.
- [ ] **Cleanup**: Delete the local file upon successful verification and refactoring.
- [ ] **Feedback**: Output the number of files updated and the new S3 URL.

### `media:download <s3_url> [local_path]`
- [ ] **Validation**: Check if URL is valid and accessible.
- [ ] **Download**: Download file from S3 to the specified `local_path` (or infer from URL if possible).
- [ ] **Refactor Content**:
    - Scan all files in `content/` (recursively).
    - Replace absolute S3 URLs with the new local relative path.
- [ ] **Feedback**: Output success message.

## Testing
- [ ] **Unit Tests**: Test the search-and-replace logic (regex/string replacement) to ensure it doesn't break other links.
- [ ] **Integration Tests**: Mock S3 client to test the flow without actual AWS calls.

## Documentation
- [ ] **Update CLI Help**: Ensure `bin/console list` shows new commands.
- [ ] **User Guide**: Add section on managing large media assets.
