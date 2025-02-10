import './bootstrap.js';

// see https://symfony.com/doc/current/frontend/asset_mapper.html#handling-3rd-party-css
// php bin/console importmap:require bootstrap
import 'bootstrap/dist/css/bootstrap.min.css';

/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
