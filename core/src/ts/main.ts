import {menuModule} from './features/menu.ts';
import {cookieModule} from './features/cookie.ts';
import {themeModule} from './features/theme.ts';
import {timeoutModule} from './features/timeout.ts';
import {toastModule} from './features/toast.ts';
import {codeModule} from './features/code.ts';
import {multiSelectModule} from './features/multi-select.ts';
import {inputModule} from './features/input.ts';
import {formTrackingModule} from './features/form-tracking.ts';
import {formValidationModule} from './features/form-validation.ts';
import {modalModule} from './features/modal.ts';
import {tableModule} from './features/table.ts';

// Imported here so Vite bundles it; local stylesheets go through Sass instead.
import './libs.css';

console.info('This website is made using the Simpl framework. Read more about Simpl here: https://www.github.com/IJuanTM/simpl/');

// Initialize modules
menuModule.init();
cookieModule.init();
themeModule.init();
timeoutModule.init();
toastModule.init();
codeModule.init();
multiSelectModule.init();
inputModule.init();
formTrackingModule.init();
formValidationModule.init();
modalModule.init();
tableModule.init();
