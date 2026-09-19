# Reads the automatic $args instead of a declared param() block - ValueFromRemainingArguments silently drops single-dash flags like -d.
switch ($args[0]) {
  { $_ -in $null, 'up', 'down', 'build', 'restart', 'stop', 'start', 'logs', 'ps', 'pull', 'config' } { docker compose @args }
  { $_ -in 'sh', 'bash' } { docker compose exec app bash }
  default { docker compose exec app composer @args }
}
