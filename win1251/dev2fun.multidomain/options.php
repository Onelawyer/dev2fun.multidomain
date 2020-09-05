<?php
/**
 * @author dev2fun (darkfriend)
 * @copyright darkfriend
 * @version 0.2.0
 */

defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Dev2fun\MultiDomain\Config;

if (!$USER->isAdmin()) {
    $APPLICATION->authForm('Nope');
}
$app = Application::getInstance();
$context = $app->getContext();
$request = $context->getRequest();
$curModuleName = "dev2fun.multidomain";
//Loc::loadMessages($context->getServer()->getDocumentRoot()."/bitrix/modules/main/options.php");
Loc::loadMessages(__FILE__);

include_once __DIR__ . '/classes/composer/vendor/autoload.php';

\Bitrix\Main\Loader::includeModule('iblock');

if ($request->isPost() && check_bitrix_sessid()) {

    $result = [
        'success' => false,
        'msg' => '',
        'data' => [],
    ];
    try {

        switch ($request->getPost('action')) {
            case 'save':
                $arFields = [];
                $arFields['logic_subdomain'] = $request->getPost('logic_subdomain');
                $arFields['type_subdomain'] = $request->getPost('type_subdomain');
                $arFields['key_ip'] = $request->getPost('key_ip');
                $arFields['domain_default'] = $request->getPost('domain_default');

                // seo tab
                $arFields['enable_seo_page'] = $request->getPost('enable_seo_page');
                $arFields['enable_seo_title_add_city'] = $request->getPost('enable_seo_title_add_city');
                $arFields['pattern_seo_title_add_city'] = $request->getPost('pattern_seo_title_add_city');

                $maplist = $request->getPost('MAPLIST');
                if ($maplist) {
                    foreach ($maplist as $k => $v) {
                        if (!$v['KEY'] || !$v['SUBNAME']) {
                            unset($maplist[$k]);
                        }
                    }
                    if ($maplist) {
                        $maplist = serialize($maplist);
                    } else {
                        $maplist = '';
                    }
                    $arFields['mapping_list'] = $maplist;
                }
                $exlist = $request->getPost('EXCLUDE_PATH');
                if ($exlist) {
                    foreach ($exlist as $k => $v) {
                        if (!$v) {
                            unset($exlist[$k]);
                        }
                    }
                    if ($exlist) {
                        $exlist = \serialize($exlist);
                    } else {
                        $exlist = '';
                    }
                    $arFields['exclude_path'] = $exlist;
                }
                $arFields['enable_multilang'] = $request->getPost('enable_multilang');
                $arFields['lang_default'] = $request->getPost('lang_default');
                $arFields['lang_default'] = $request->getPost('lang_default');

                foreach ($arFields as $k => $arField) {
                    Option::set($curModuleName, $k, $arField);
                }

                $langFields = $request->getPost('lang_fields');
                if ($langFields) {
                    $hl = \Darkfriend\HLHelpers::getInstance();
                    $elements = [];
                    foreach ($langFields as $langField) {
                        if (isset($elements[$langField['iblock']])) {
                            continue;
                        }
                        $elements[$langField['iblock']] = $hl->getElementList(
                            Config::getInstance()->get('lang_fields'),
                            ['UF_IBLOCK_ID' => $langField['iblock']]
                        );
                    }

                    if ($elements) {
                        foreach ($elements as $k => $elementFields) {
                            foreach ($elementFields as $eKey => $element) {
                                $elements[$element['UF_IBLOCK_ID'] . $element['UF_FIELD_TYPE'] . $element['UF_FIELD']] = $element;
                                unset($elements[$k][$eKey]);
                            }
                        }
                    }

                    $addedFields = [];
                    foreach ($langFields as $langField) {
                        if (isset($elements[$langField['iblock'] . $langField['fieldType'] . $langField['field']])) {
                            unset($elements[$langField['iblock'] . $langField['fieldType'] . $langField['field']]);
                            continue;
                        }
                        if (\in_array($langField['iblock'] . $langField['fieldType'] . $langField['field'], $addedFields)) {
                            continue;
                        }
                        //                        \darkfriend\helpers\DebugHelper::print_pre([
                        //                            'UF_IBLOCK_ID' => $langField['iblock'],
                        //                            'UF_FIELD' => $langField['field'],
                        //                            'UF_FIELD_TYPE' => $langField['fieldType'],
                        //                        ]);
                        $hl->addElement(
                            Config::getInstance()->get('lang_fields'),
                            [
                                'UF_IBLOCK_ID' => $langField['iblock'],
                                'UF_FIELD' => $langField['field'],
                                'UF_FIELD_TYPE' => $langField['fieldType'],
                            ]
                        );
                        $addedFields[] = $langField['iblock'] . $langField['fieldType'] . $langField['field'];
                    }
                    if ($elements) {
                        foreach ($elements as $element) {
                            if (empty($element)) continue;
                            $hl->deleteElement(Config::getInstance()->get('lang_fields'), $element['ID']);
                        }
                    }
                }

                $result['msg'] = 'Настройки успешно сохранены';
                break;
            case 'getIblocks':
                $rsIblocks = CIBlock::GetList(['NAME' => 'ASC'], ['ACTIVE' => 'Y']);
                $result['data']['groups'] = [
                    [
                        'id' => 'iblock',
                        'label' => 'IBlocks',
                    ],
                ];
                while ($iblock = $rsIblocks->GetNext()) {
                    $result['data']['items'][] = [
                        'id' => $iblock['ID'],
                        'label' => "{$iblock['NAME']} [{$iblock['ID']}]",
                        'group' => 'iblock',
                    ];
                }
                $hlIblocks = \Darkfriend\HLHelpers::getInstance()->getList();
                if ($hlIblocks) {
                    $result['data']['groups'][] = [
                        'id' => 'hl',
                        'label' => 'Highload Blocks',
                    ];
                    foreach ($hlIblocks as $hlIblock) {
                        $result['data']['items'][] = [
                            'id' => 'HL' . $hlIblock['ID'],
                            'label' => "{$hlIblock['NAME']}",
                            'group' => 'hl',
                        ];
                    }
                }
                break;
            case 'getFields':
                $id = $request->getPost('id');
                if (\strpos($id, 'HL') === false) {
                    $result['data']['groups'] = [
                        [
                            'id' => 'field',
                            'label' => 'Fields',
                        ],
                        [
                            'id' => 'prop',
                            'label' => 'Properties',
                        ],
                    ];
                    //                    foreach (CIBlock::GetFields($id) as $code=>$field) {
                    $iblockFields = [
                        'NAME',
                        //                        'PREVIEW_PICTURE',
                        'PREVIEW_TEXT',
                        //                        'DETAIL_PICTURE',
                        'DETAIL_TEXT',
                    ];
                    foreach ($iblockFields as $code) {
                        $result['data']['items'][] = [
                            'id' => $code,
                            'label' => $code,
                            'group' => 'field',
                        ];
                    }
                    $rsIblocks = CIBlock::GetProperties($id, ['NAME' => 'ASC'], ['ACTIVE' => 'Y']);
                    while ($iblock = $rsIblocks->GetNext()) {
                        $result['data']['items'][] = [
                            'id' => $iblock['ID'],
                            'label' => $iblock['NAME'],
                            'group' => 'prop',
                        ];
                    }
                } else {
                    $id = \str_replace('HL', '', $id);
                    $result['data']['groups'][] = [
                        'id' => 'prop',
                        'label' => 'Properties',
                    ];
                    $fields = \Darkfriend\HLHelpers::getInstance()->getFields($id);
                    foreach ($fields as $code => $field) {
                        $result['data']['items'][] = [
                            'id' => $code,
                            'label' => $field->getName(),
                            'group' => 'prop',
                        ];
                    }
                }

                break;
            case 'getFieldsSection':
                $id = $request->getPost('id');
                $result['data']['groups'] = [
                    [
                        'id' => 'field',
                        'label' => 'Fields',
                    ],
                    [
                        'id' => 'prop',
                        'label' => 'Properties',
                    ],
                ];
                //                    foreach (CIBlock::GetFields($id) as $code=>$field) {
                $iblockFields = [
                    'NAME',
                    'PICTURE',
                    'DESCRIPTION',
                    'DETAIL_PICTURE',
                ];
                foreach ($iblockFields as $code) {
                    $result['data']['items'][] = [
                        'id' => $code,
                        'label' => $code,
                        'group' => 'field',
                    ];
                }
                $rsData = CUserTypeEntity::GetList(['FIELD_NAME' => 'ASC'], ['ENTITY_ID' => "IBLOCK_{$id}_SECTION"]);
                while ($arField = $rsData->GetNext()) {
                    $result['data']['items'][] = [
                        'id' => $arField['FIELD_NAME'],
                        'label' => $arField['FIELD_NAME'],
                        'group' => 'prop',
                    ];
                }
                break;
        }


        $result['success'] = true;
    } catch (\Exception $e) {
        $result['msg'] = 'Ошибка в сохранении настроек';
    }

    $APPLICATION->RestartBuffer();
    \darkfriend\helpers\Response::json($result, [
        'show' => true,
        'die' => true,
    ]);
}
$msg = new CAdminMessage([
    'MESSAGE' => Loc::getMessage("D2F_MULTIDOMAIN_DONATE_MESSAGES", ['#URL#' => '/bitrix/admin/settings.php?lang=ru&mid=dev2fun.multidomain&mid_menu=1&tabControl_active_tab=donate']),
    'TYPE' => 'OK',
    'HTML' => true,
]);
echo $msg->Show();

$assets = \Bitrix\Main\Page\Asset::getInstance();
$assets->addJs('/bitrix/js/' . $curModuleName . '/script.js');
?>

<link rel="stylesheet" href="https://unpkg.com/blaze@4.0.0-6/scss/dist/components.cards.min.css">
<link rel="stylesheet" href="https://unpkg.com/blaze@4.0.0-6/scss/dist/objects.grid.min.css">
<link rel="stylesheet" href="https://unpkg.com/blaze@4.0.0-6/scss/dist/objects.grid.responsive.min.css">
<link rel="stylesheet" href="https://unpkg.com/blaze@4.0.0-6/scss/dist/objects.containers.min.css">
<link rel="stylesheet" href="https://unpkg.com/blaze@4.0.0-6/scss/dist/components.tables.min.css">

<?php
//$vueScripts = [
//    '/bitrix/modules/dev2fun.multidomain/frontend/dist/js/main.bundle.js',
//    '/bitrix/modules/dev2fun.multidomain/frontend/dist/js/polyfill.bundle.js',
//];
$vueScripts = [
    '/bitrix/js/dev2fun.multidomain/vue/main.bundle.js',
    '/bitrix/js/dev2fun.multidomain/vue/polyfill.bundle.js',
];
foreach ($vueScripts as $script) {
    $assets->addJs($script);
    //    echo "<script src='{$script}?".filemtime($_SERVER['DOCUMENT_ROOT'].$script)."' async defer></script>";
}
$mappingList = Option::get($curModuleName, "mapping_list", [['KEY' => '', 'SUBNAME' => '']]);
if ($mappingList && \is_string($mappingList)) {
    $mappingList = \unserialize($mappingList);
}
$excludeList = Option::get($curModuleName, "exclude_path", ['\/(bitrix|local)\/(admin|tools)\/']);
if ($excludeList && \is_string($excludeList)) {
    $excludeList = \unserialize($excludeList);
}
$hl = \Darkfriend\HLHelpers::getInstance();
$langFields = $hl->getElementList(Config::getInstance()->get('lang_fields'));
if ($langFields) {
    foreach ($langFields as &$langField) {
        $langField = [
            'iblock' => $langField['UF_IBLOCK_ID'],
            'field' => $langField['UF_FIELD'],
            'fieldType' => $langField['UF_FIELD_TYPE'],
        ];
    }
    unset($langField);
}
$paramsObject = \CUtil::phpToJSObject([
    'logic_subdomain' => Option::get($curModuleName, "logic_subdomain", 'virtual'),
    'type_subdomain' => Option::get($curModuleName, "type_subdomain", 'country'),
    'key_ip' => Option::get($curModuleName, "key_ip", 'REMOTE_ADDR'),
    'domain_default' => Option::get($curModuleName, "domain_default", $_SERVER['HTTP_HOST']),
    'MAPLIST' => $mappingList,
    'EXCLUDE_PATH' => $excludeList,

    'enable_multilang' => Option::get($curModuleName, "enable_multilang", false),
    'lang_default' => Option::get($curModuleName, "lang_default", 'ru'),
    'lang_fields' => $langFields,

    'enable_seo_page' => Option::get($curModuleName, "enable_seo_page", false),
    'enable_seo_title_add_city' => Option::get($curModuleName, "enable_seo_title_add_city", false),
    'pattern_seo_title_add_city' => Option::get($curModuleName, "#TITLE# - #CITY#", false),
]);
$settingsObject = \CUtil::phpToJSObject([
    'remoteAddr' => $_SERVER['REMOTE_ADDR'],
    'realIp' => $_SERVER['HTTP_X_REAL_IP'],
]);
$formObject = \CUtil::phpToJSObject([
    'sessid' => bitrix_sessid_val(),
    'action' => \sprintf('%s?mid=%s&lang=%s', $request->getRequestedPage(), \urlencode($mid), \LANGUAGE_ID),
]);
$localeObject = \CUtil::phpToJSObject([
    'MAIN_TAB_SET' => Loc::getMessage("MAIN_TAB_SET"),
    'D2F_MULTIDOMAIN_MAIN_TAB_SETTINGS' => Loc::getMessage("D2F_MULTIDOMAIN_MAIN_TAB_SETTINGS"),
    'MAIN_TAB_TITLE_SET' => Loc::getMessage("MAIN_TAB_TITLE_SET"),

    'D2F_MULTIDOMAIN_TAB_2' => Loc::getMessage("D2F_MULTIDOMAIN_TAB_2"),
    'D2F_MULTIDOMAIN_TAB_2_TITLE_SET' => Loc::getMessage("D2F_MULTIDOMAIN_TAB_2_TITLE_SET"),

    'D2F_MULTIDOMAIN_TAB_3' => Loc::getMessage("D2F_MULTIDOMAIN_TAB_3"),
    'D2F_MULTIDOMAIN_TAB_3_TITLE_SET' => Loc::getMessage("D2F_MULTIDOMAIN_TAB_3_TITLE_SET"),

    'D2F_MULTIDOMAIN_TAB_4' => Loc::getMessage("D2F_MULTIDOMAIN_TAB_4"),
    'D2F_MULTIDOMAIN_TAB_4_TITLE_SET' => Loc::getMessage("D2F_MULTIDOMAIN_TAB_4_TITLE_SET"),

    'SEC_DONATE_TAB' => Loc::getMessage("SEC_DONATE_TAB"),
    'SEC_DONATE_TAB_TITLE' => Loc::getMessage("SEC_DONATE_TAB_TITLE"),


    'LABEL_ALGORITM' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_ALGORITM"),
    'LABEL_VIRTUAL' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_VIRTUAL"),
    'LABEL_SUBDOMAIN' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_SUBDOMAIN"),
    'LABEL_DIRECTORY' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_DIRECTORY"),

    'LABEL_TYPE' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_TYPE"),
    'LABEL_CITY' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_CITY"),
    'LABEL_COUNTRY' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_COUNTRY"),
    'DESCRIPTION_TYPE' => Loc::getMessage("D2F_MULTIDOMAIN_DESCRIPTION_TYPE"),
    'LABEL_IP' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_IP"),
    'LABEL_DOMAIN_DEFAULT' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_DOMAIN_DEFAULT"),
    'DESCRIPTION_DOMAIN_DEFAULT' => Loc::getMessage("D2F_MULTIDOMAIN_DESCRIPTION_DOMAIN_DEFAULT"),
    'LABEL_MAPPING_LIST' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_MAPPING_LIST"),
    'LABEL_MAPPING_LIST_KEY' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_MAPPING_LIST_KEY"),
    'LABEL_MAPPING_LIST_SUBNAME' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_MAPPING_LIST_SUBNAME"),
    'LABEL_ADD' => Loc::getMessage("LABEL_ADD"),
    'D2F_MULTIDOMAIN_LABEL_DELETE' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_DELETE"),
    'D2F_MULTIDOMAIN_PLACEHOLDER_TYPE' => Loc::getMessage("D2F_MULTIDOMAIN_PLACEHOLDER_TYPE"),
    'D2F_MULTIDOMAIN_LABEL_SUPPORT_TRANSLATE' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_SUPPORT_TRANSLATE"),

    'LABEL_EXCLUDE_PATH' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_EXCLUDE_PATH"),
    'LABEL_EXCLUDE_PATH_REG' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_EXCLUDE_PATH_REG"),

    'LABEL_ENABLE_MULTILANG' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_ENABLE_MULTILANG"),
    'LABEL_LANG_DEFAULT' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_LANG_DEFAULT"),
    'LABEL_LANG_SUPPORT_FIELDS' => 'Поле с поддержкой перевода',

    'D2F_MULTIDOMAIN_LABEL_TAB_SELECT_ALL' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_TAB_SELECT_ALL"),
    'D2F_MULTIDOMAIN_LABEL_TAB_COLLAPSE' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_TAB_COLLAPSE"),


    'DOMAIN_LIST_H2' => Loc::getMessage("D2F_MULTIDOMAIN_DOMAIN_LIST_H2"),
    'D2F_MULTIDOMAIN_SUBDOMAIN_LIST_NOTE' => \htmlspecialchars(Loc::getMessage("D2F_MULTIDOMAIN_SUBDOMAIN_LIST_NOTE", [
        '#ID#' => \Bitrix\Main\Config\Option::get($curModuleName, "highload_domains"),
    ])),
    'LABEL_ENABLE_SEO_PAGE' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_ENABLE_SEO_PAGE"),
    'LABEL_ENABLE_SEO_TITLE_ADD_CITY' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_ENABLE_SEO_TITLE_ADD_CITY"),
    'LABEL_PATTERN_SEO_TITLE_ADD_CITY' => Loc::getMessage("D2F_MULTIDOMAIN_LABEL_PATTERN_SEO_TITLE_ADD_CITY"),

    // donate
    'LABEL_TITLE_HELP_BEGIN' => \htmlspecialchars(Loc::getMessage("LABEL_TITLE_HELP_BEGIN")),
    'LABEL_TITLE_HELP_BEGIN_TEXT' => \htmlspecialchars(Loc::getMessage("LABEL_TITLE_HELP_BEGIN_TEXT")),
    'LABEL_TITLE_HELP_DONATE_TEXT' => \htmlspecialchars(Loc::getMessage("LABEL_TITLE_HELP_DONATE_TEXT")),
    'LABEL_TITLE_HELP_DONATE_ALL_TEXT' => \htmlspecialchars(Loc::getMessage("LABEL_TITLE_HELP_DONATE_ALL_TEXT")),
    'LABEL_TITLE_HELP_DONATE_OTHER_TEXT' => \htmlspecialchars(Loc::getMessage("LABEL_TITLE_HELP_DONATE_OTHER_TEXT")),
    'LABEL_TITLE_HELP_DONATE_OTHER_TEXT_S' => \htmlspecialchars(Loc::getMessage("LABEL_TITLE_HELP_DONATE_OTHER_TEXT_S")),
    'LABEL_TITLE_HELP_DONATE_FOLLOW' => \htmlspecialchars(Loc::getMessage("LABEL_TITLE_HELP_DONATE_FOLLOW")),
]);
//    var_dump(Option::get($curModuleName, "exclude_path", ['\/(bitrix|local)\/(admin|tools)\/']));
//    var_dump($paramsObject);
//    var_dump($settingsObject);
?>
<div id="dev2funMultiDomain">
    <app
        :input-value="<?= $paramsObject ?>"
        :settings="<?= $settingsObject ?>"
        :form-settings="<?= $formObject ?>"
        :locale="<?= $localeObject ?>"
    />
</div>