// @addon-insert:prepend
import {inputModule} from './features/input.ts';
import {messageModule} from './features/message.ts';
import {formTrackingModule} from './features/form-tracking.ts';
import {profileImageModule} from './features/profile-image.ts';
import {tableModule} from './features/table.ts';
import {verificationModule} from './features/verification-code.ts';
import {modalModule} from './features/modal.ts';
import {logoutModule} from './features/logout.ts';
// @addon-end

// @addon-insert:after('// Initialize modules')
inputModule.init();
messageModule.init();
formTrackingModule.init();
profileImageModule.init();
tableModule.init();
verificationModule.init();
modalModule.init();
logoutModule.init();
// @addon-end
