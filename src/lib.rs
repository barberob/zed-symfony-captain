use std::fs;

use zed_extension_api::{
    self as zed, settings::LspSettings, DownloadedFileType, LanguageServerInstallationStatus,
};

/// The release tag whose `symfony-captain-lsp.php` asset is downloaded on first
/// use. Keep in sync with the `version` in `extension.toml` and the tag pushed
/// to trigger `.github/workflows/release.yml`.
const LSP_RELEASE_TAG: &str = "v0.1.0";
const LSP_SCRIPT_NAME: &str = "symfony-captain-lsp.php";
const LSP_REPOSITORY: &str = "symfony-captain/symfony-captain";

fn lsp_download_url() -> String {
    format!(
        "https://github.com/{repository}/releases/download/{tag}/{script}",
        repository = LSP_REPOSITORY,
        tag = LSP_RELEASE_TAG,
        script = LSP_SCRIPT_NAME,
    )
}

struct SymfonyCaptainExtension {
    cached_lsp_path: Option<String>,
}

impl SymfonyCaptainExtension {
    /// Returns the path to the LSP script, either from the user setting override
    /// or from the release asset downloaded into the extension working
    /// directory on first use.
    fn lsp_path(
        &mut self,
        language_server_id: &zed::LanguageServerId,
        override_path: Option<String>,
    ) -> Result<String, String> {
        if let Some(override_path) = override_path {
            return Ok(override_path);
        }

        if let Some(cached_path) = self.cached_lsp_path.clone() {
            return Ok(cached_path);
        }

        let work_dir = std::env::current_dir()
            .map_err(|error| format!("cannot resolve extension working directory: {error}"))?;
        let script_path = work_dir.join(LSP_SCRIPT_NAME);
        let script_path = script_path.to_string_lossy().into_owned();

        if !fs::metadata(&script_path).is_ok_and(|metadata| metadata.is_file()) {
            zed::set_language_server_installation_status(
                language_server_id,
                &LanguageServerInstallationStatus::Downloading,
            );

            let result = zed::download_file(
                &lsp_download_url(),
                &script_path,
                DownloadedFileType::Uncompressed,
            );

            zed::set_language_server_installation_status(
                language_server_id,
                &LanguageServerInstallationStatus::None,
            );

            result.map_err(|error| format!("failed to download {LSP_SCRIPT_NAME}: {error}"))?;
        }

        self.cached_lsp_path = Some(script_path.clone());

        Ok(script_path)
    }
}

impl zed::Extension for SymfonyCaptainExtension {
    fn new() -> Self {
        Self {
            cached_lsp_path: None,
        }
    }

    fn language_server_command(
        &mut self,
        language_server_id: &zed::LanguageServerId,
        worktree: &zed::Worktree,
    ) -> Result<zed::Command, String> {
        let binary_settings = LspSettings::for_worktree("symfony-captain", worktree)
            .ok()
            .and_then(|settings| settings.binary);

        let extra_arguments = binary_settings
            .as_ref()
            .and_then(|binary| binary.arguments.clone())
            .unwrap_or_default();

        let php_path = worktree
            .which("php")
            .ok_or_else(|| "php not found on PATH".to_string())?;

        let lsp_path = self.lsp_path(
            language_server_id,
            binary_settings.and_then(|binary| binary.path),
        )?;

        let mut arguments = vec![lsp_path];
        arguments.extend(extra_arguments);

        Ok(zed::Command {
            command: php_path,
            args: arguments,
            env: worktree.shell_env(),
        })
    }
}

zed::register_extension!(SymfonyCaptainExtension);
