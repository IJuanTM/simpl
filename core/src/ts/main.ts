import {menuModule} from './features/menu.ts';
import {cookieModule} from './features/cookie.ts';
import {themeModule} from './features/theme.ts';
import {timeoutModule} from './features/timeout.ts';
import {codeModule} from './features/code.ts';
import {multiSelectModule} from './features/multi-select.ts';

// Imported here so Vite bundles it; local stylesheets go through Sass instead.
import './libs.css';

console.info('This website is made using the Simpl framework. Read more about Simpl here: https://www.github.com/IJuanTM/simpl/');

// Initialize modules
menuModule.init();
cookieModule.init();
themeModule.init();
timeoutModule.init();
codeModule.init();
multiSelectModule.init();
