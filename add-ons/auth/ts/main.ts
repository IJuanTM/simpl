// @addon-insert:prepend
import {inputModule} from './features/input.ts';
import {messageModule} from './features/message.ts';
import {formTrackingModule} from './features/form-tracking.ts';
import {profileModule} from './features/profile.ts';
import {verificationModule} from './features/verification-code.ts';
// @addon-end

// @addon-insert:after('// Initialize modules')
inputModule.init();
messageModule.init();
formTrackingModule.init();
profileModule.init();
verificationModule.init();
// @addon-end
