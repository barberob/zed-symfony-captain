use zed_extension_api::{
    self as zed,
    settings::LspSettings,
};

struct SymfonyCaptainExtension;

impl zed::Extension for SymfonyCaptainExtension {
    fn new() -> Self {
        Self
    }

    fn language_server_command(
        &mut self,
        _language_server_id: &zed::LanguageServerId,
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

        let lsp_path = binary_settings
            .and_then(|binary| binary.path)
            .ok_or_else(|| {
                "Set lsp.symfony-captain.binary.path to the symfony-captain-lsp.php script".to_string()
            })?;

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
