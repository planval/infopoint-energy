const dotenv = require('dotenv');

const loadEnv = function () {

    let result = { ...dotenv.config({ path: '.env' }).parsed };

    let envLocal = dotenv.config({ path: '.env.local' });

    if (envLocal.error) {
        return result;
    }

    for(let key in envLocal.parsed) {
        if(envLocal.parsed.hasOwnProperty(key)) {
            result[key] = envLocal.parsed[key];
        }
    }

    return result;

}

const env = loadEnv();

const sassVariables = `

    $app-env: "${env.APP_ENV}";
    $instance-id: "${env.INSTANCE_ID}";
    $instance-name: "${env.INSTANCE_NAME}";
    $theme-color-1: ${env.THEME_COLOR_1};
    $theme-color-2: ${env.THEME_COLOR_2};
    $theme-color-3: ${env.THEME_COLOR_3};
    $theme-color-4: ${env.THEME_COLOR_4};
    $theme-color-5: ${env.THEME_COLOR_5};
`;

const embedSassVariables = `

    $app-env: "${env.APP_ENV}";
    $instance-id: "${env.INSTANCE_ID}";
    $instance-name: "${env.INSTANCE_NAME}";
    $primary-color: #B4BE00;
    $secondary-color: #D3E292;
    $black: #000;
    $white: #FFF;
    $grey-light: #d9d9d9;
    $grey-mid: #979797;
    $grey-dark: #333333;

`;

module.exports = {
    env,
    sassVariables,
    embedSassVariables,
};