# Thin wrapper around `docker compose` - anything not one of Compose's own commands runs as
# `composer <args>` inside the app container, so `./simpl.ps1 migrate` replaces
# `docker compose exec app composer migrate`.
#
# Deliberately reads the automatic $args variable instead of a declared param() block - PowerShell's
# parameter binder silently drops single-dash short flags (e.g. `-d`) passed to a declared
# [Parameter(ValueFromRemainingArguments)] array, since it tries to bind them as named parameters first.
switch ($args[0]) {
  { $_ -in $null, 'up', 'down', 'build', 'restart', 'stop', 'start', 'logs', 'ps', 'pull', 'config' } { docker compose @args }
  { $_ -in 'sh', 'bash' } { docker compose exec app bash }
  default { docker compose exec app composer @args }
}
