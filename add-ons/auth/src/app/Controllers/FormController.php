@addon-insert:replace('// @addon-placeholder')
/**
* Validate password fields: check policy on the first field and match with the second.
* Clears invalid fields and adds alerts on failure.
*
* @param string $passwordField
* @param string $confirmField
*
* @return bool True if valid, false otherwise
*/
public static function validatePasswords(string $passwordField, string $confirmField): bool
{
$password = RequestController::rawPost($passwordField);
$confirm = RequestController::rawPost($confirmField);

if (!AuthController::validatePassword((string)$password)) {
$_POST[$passwordField] = '';
$_POST[$confirmField] = '';
return false;
}

if ($password !== $confirm) {
$_POST[$passwordField] = '';
$_POST[$confirmField] = '';
static::addAlert('The entered passwords do not match!', AlertType::WARNING);
return false;
}

return true;
}
@addon-end
