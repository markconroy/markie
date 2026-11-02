# Security considerations

AI Validations adds Field Validation constraints that send field data (text,
image, or audio content) to a configured AI Provider (via the AI module) for
evaluation. Site builders should be aware of the following before enabling
these constraints:

- **Data sent to third parties.** Field content submitted through a
  validated form is sent to whichever AI Provider is configured (e.g. an
  external API). Do not enable AI-based validation on fields that may
  contain sensitive or personal data unless the configured provider's data
  handling policy is acceptable for your use case.
- **Provider trust boundary.** The module trusts the response returned by
  the configured AI Provider to determine pass/fail. A misconfigured or
  compromised provider integration could cause validations to pass or fail
  incorrectly; this module does not add its own content moderation beyond
  what the provider returns.
- **Access control.** Configuration of AI Validations constraints requires
  the same permissions as Field Validation rule administration. No
  additional permissions are introduced by this module.
- **No direct user input execution.** Prompts used by the text/image
  constraints are defined by the site builder in configuration, not by the
  end user submitting the form; end-user field values are passed as data to
  the provider, not concatenated into executable code.
- **Prompt injection can influence the verdict.** The text/image/audio
  "boolean chat" constraints send the site builder's prompt and the
  end-user's field content to the provider in the same conversation. This is
  not code execution, but a user can still attempt to steer the model's
  verdict with instructions embedded in their own field content (e.g. asking
  the model to ignore the prompt and answer a certain way). The module
  mitigates naive attempts by requiring the model's response to *start* with
  the exact XTRUE/XFALSE token rather than merely mention it, but this is a
  best-effort mitigation, not a guarantee: a sufficiently effective prompt
  injection could still influence a capable model's output. Do not rely on
  these constraints as the sole safeguard against malicious content in
  security-critical contexts.

To report a security issue in this module, please follow the
[Drupal Security Team's reporting process](https://www.drupal.org/drupal-security-team/general-information/reporting-issues).
