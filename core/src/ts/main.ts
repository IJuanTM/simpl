import {menuModule} from './features/menu.ts';
import {cookieModule} from './features/cookie.ts';
import {themeModule} from './features/theme.ts';
import {timeoutModule} from './features/timeout.ts';
import {codeModule} from './features/code.ts';

// Import external stylesheets for Vite to bundle them, local stylesheets are handled by sass
import './libs.css';

// Simpl attribution
console.info('This website is made using the Simpl framework. Read more about Simpl here: https://www.github.com/IJuanTM/simpl/');

// Initialize modules
menuModule.init();
cookieModule.init();
themeModule.init();
timeoutModule.init();
codeModule.init();
