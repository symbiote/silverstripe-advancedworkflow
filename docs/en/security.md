## Security

### `whitelist_template_variables`

The `NotifyUsersWorkflowAction` workflow action has a configuration parameter, `whitelist_template_variables`.
Currently this variable defaults to false in order to retain backwards compatibility. In a future major release it will
be changed to default to true.

Setting this configuration variable to true will limit template variables available in the email template sent as part
of the notify users action to a known-safe whitelist. When it is false, the template may reference any accessible parameter.
As this template is editable in the CMS, whitelisting these parameters ensures CMS admins can not bypass data access
restrictions.