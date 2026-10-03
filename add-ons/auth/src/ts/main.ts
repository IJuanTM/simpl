// @addon-insert:prepend
import {messageModule} from './features/message.ts';
import {profileImageModule} from './features/profile-image.ts';
import {adminTableModule} from './features/admin-table.ts';
import {verificationModule} from './features/verification-code.ts';
import {adminModalsModule} from './features/admin-modals.ts';
import {logoutModule} from './features/logout.ts';
import {reservedUsernameModule} from './features/reserved-username.ts';
import {passkeyModule} from './features/passkey.ts';
import {twoFactorModule} from './features/two-factor.ts';
// @addon-end

// @addon-insert:after('// Initialize modules')
messageModule.init();
profileImageModule.init();
adminTableModule.init();
verificationModule.init();
adminModalsModule.init();
logoutModule.init();
reservedUsernameModule.init();
passkeyModule.init();
twoFactorModule.init();
// @addon-end
