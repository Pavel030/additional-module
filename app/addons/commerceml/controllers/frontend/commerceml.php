<?php
/***************************************************************************
 *                                                                          *
 *   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 *                                                                          *
 ****************************************************************************
 * PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
 * "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
 ****************************************************************************/

use Tygh\Addons\CommerceML\Commands\RemoveImportCommand;
use Tygh\Addons\CommerceML\Commands\CleanUpFilesDirCommand;
use Tygh\Addons\CommerceML\ServiceProvider;
use Tygh\Addons\CommerceML\Commands\AuthCommand;
use Tygh\Addons\CommerceML\Commands\UploadImportFileCommand;
use Tygh\Addons\CommerceML\Commands\UnzipImportFileCommand;
use Tygh\Addons\CommerceML\Commands\CreateImportCommand;
use Tygh\Addons\CommerceML\Commands\ExecuteCatalogImportCommand;
use Tygh\Addons\CommerceML\Commands\ExportOrdersCommand;
use Tygh\Addons\CommerceML\Commands\ExecuteSaleImportCommand;
use Tygh\Addons\CommerceML\Dto\ImportDto;
use Tygh\Addons\CommerceML\Dto\OrderDto;
use Tygh\Addons\CommerceML\Dto\ProductDto;
use Tygh\Registry;

defined('BOOTSTRAP') or die('Access denied');

if (empty($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Authorization required"');
    header('HTTP/1.0 401 Unauthorized');
    fn_echo('Enter user login and password');
    exit;
}

fn_define('COMMERCEML_IMPORT', true);

/** @var \Tygh\Web\Session $session */
$session = Tygh::$app['session'];

if (!$session->isStarted()) {
    $session->start();
}

$command_bus = ServiceProvider::getCommandBus();

$auth_result = $command_bus->dispatch(
    AuthCommand::createFromServer($_SERVER)
);

if (!$auth_result->isSuccess()) {
    fn_echo("\n" . $auth_result->getFirstError());
    exit;
}

$user_data = $auth_result->getData();
$session['auth'] = fn_fill_auth($user_data);
$params = $_REQUEST;

$type = isset($params['type']) ? $params['type'] : '';
$mode = isset($params['mode']) ? $params['mode'] : '';
$action = isset($params['action']) ? $params['action'] : '';
$mode = sprintf('%s_%s', $type, $mode);
$filename = isset($params['filename']) ? fn_basename($params['filename']) : null;
$is_import_file = $filename && strpos($filename, 'import') !== false;
$user_id = (int) $user_data['user_id'];

if (fn_allowed_for('ULTIMATE')) {
    $company_id = fn_get_runtime_company_id();
} else {
    $company_id = (int) $user_data['company_id'];
    Registry::set('runtime.company_id', $company_id);
}
$logger = ServiceProvider::getLogger();

if (!isset($session['commerecml_import_key'])) {
    $key = 'last_commerecml_import_key_' . $company_id;
    $key_time = 'last_commerecml_import_key_timer_' . $company_id;

    if (fn_is_expired_storage_data($key_time, SECONDS_IN_HOUR)) {
        fn_set_storage_data($key);
    }

    $import_key = fn_get_storage_data($key);

    if (!$import_key) {
        $import_key = md5((string) time());
        fn_set_storage_data($key, $import_key);
    }

    $session['commerecml_import_key'] = $import_key;
}

$import_key = $session['commerecml_import_key'];
$upload_dir_path = ServiceProvider::getUploadDirPath();

ServiceProvider::clearExpiredEntities();

if (ServiceProvider::isManualMappingRequired($company_id)) {
    $action = 'analyze';
}

if ($mode === 'catalog_checkauth' || $mode === 'sale_checkauth') {
    $expired_storage_entries = db_get_fields(
        'SELECT data_key FROM ?:storage_data WHERE data_key LIKE ?l AND data < ?i',
        'commerceml_import_file_timer%',
        strtotime('-1 day')
    );
    if ($expired_storage_entries) {
        foreach ($expired_storage_entries as $entry_key) {
            $entry_bits = [];
            if (
                preg_match(
                    '/commerceml_import_file_timer_(?P<company_id>\d*)_(?P<filename>[\w\-]*\.xml)(_)?(?P<session_id>.*)/',
                    $entry_key,
                    $entry_bits
                )
            ) {
                $company_id = $entry_bits['company_id'] ?: '';
                $filename = $entry_bits['filename'] ?: '';
                $session_id = $entry_bits['session_id'] ? '_' . $entry_bits['session_id'] : '';
                db_query(
                    'DELETE FROM ?:storage_data WHERE data_key = ?s',
                    'commerceml_import_file_' . $company_id . '_' . $filename . $session_id
                );
                db_query(
                    'DELETE FROM ?:storage_data WHERE data_key = ?s',
                    'commerceml_import_file_timer_' . $company_id . '_' . $filename . $session_id
                );
            }
        }
    }
    $logger->info(__('commerceml.controller.start_handle_catalog_checkauth_request'));

    fn_echo("success\n");
    fn_echo($session->getName() . "\n");
    fn_echo($session->getID());

    $logger->info(__('commerceml.controller.end_handle_catalog_checkauth_request', ['[session_id]' => $session->getID()]));
} elseif ($mode === 'catalog_init' || $mode === 'sale_init') {
    $logger->info(__('commerceml.controller.start_handle_catalog_init'));

    if (ServiceProvider::isZipAllowed()) {
        fn_echo("zip=yes\n");
    } else {
        fn_echo("zip=no\n");
    }

    fn_echo('file_limit=' . ServiceProvider::getUploadFileLimit() . "\n");

    $command_bus->dispatch(CleanUpFilesDirCommand::create($upload_dir_path));
    $session['old_files_cleanup'] = true;

    $logger->info(__('commerceml.controller.upload_directory_cleaned', ['[dir]' => fn_get_rel_dir($upload_dir_path)]));
    $logger->info(__('commerceml.controller.end_handle_catalog_init', [
        '[limit]' => ServiceProvider::getUploadFileLimit(),
        '[zip]'   => ServiceProvider::isZipAllowed() ? 'yes' : 'no'
    ]));
} elseif ($mode === 'catalog_file') {
    $logger->info(__('commerceml.controller.start_handle_catalog_file', ['[filename]' => $filename]));

    if (!isset($session['uploaded_zip_files'])) {
        $session['uploaded_zip_files'] = [];
    }

    if (empty($filename)) {
        fn_echo('failure');
        $logger->error(__('commerceml.controller.error', ['[message]' => 'Filename is empty']));
        exit;
    }

    $result = $command_bus->dispatch(
        UploadImportFileCommand::create($filename, $upload_dir_path)
    );

    $logger->logResult($result);

    if ($result->isSuccess()) {
        if (fn_strtolower(fn_get_file_ext($filename)) === 'zip') {
            $file_path = $result->getData('file_path', false);
            $session['uploaded_zip_files'][$file_path] = $file_path;
        }

        $session['converted_files'] = [];
        fn_echo('success');
    } else {
        fn_echo('failure');
    }

    $logger->info(__('commerceml.controller.end_handle_catalog_file', ['[filename]' => $filename]));
} elseif ($mode === 'catalog_import' && !empty($session['uploaded_zip_files'])) {
    $file_path = reset($session['uploaded_zip_files']);
    $logger->info(__('commerceml.controller.start_handle_catalog_import_unzip', ['[filename]' => basename($file_path)]));

    $result = $command_bus->dispatch(
        UnzipImportFileCommand::create($file_path, dirname($file_path))
    );

    $logger->logResult($result);

    if (!$result->isSuccess()) {
        fn_echo('failure');
    } else {
        unset($session['uploaded_zip_files'][$file_path]);
        fn_echo('progress');
    }

    $logger->info(__('commerceml.controller.end_handle_catalog_import_unzip', ['[filename]' => basename($file_path)]));
} elseif ($mode === 'catalog_import' && $filename && empty($session['converted_files'][$filename])) {
    $session_id = $session->getID();
    $import_state = fn_get_storage_data("commerceml_import_file_{$company_id}_{$filename}_{$session_id}", false);

    if ($import_state) {
        if (
            $import_state === 'failure'
            || (
                fn_get_storage_data("commerceml_import_file_timer_{$company_id}_{$filename}_{$session_id}", false)
                && fn_is_expired_storage_data("commerceml_import_file_timer_{$company_id}_{$filename}_{$session_id}")
            )
        ) {
            fn_set_storage_data("commerceml_import_file_{$company_id}_{$filename}_{$session_id}");
            fn_set_storage_data("commerceml_import_file_timer_{$company_id}_{$filename}_{$session_id}");

            fn_echo('failure');
            exit;
        }

        fn_echo($import_state);
        exit;
    }

    $logger->info(__('commerceml.controller.start_handle_catalog_import_convert', ['[filename]' => $filename]));

    if ($is_import_file && !ServiceProvider::isCatalogImportEnabled($company_id)) {
        fn_echo('success');
        $logger->info(__('commerceml.controller.convert_import_file_skiped_by_import_mode', ['[filename]' => $filename]));
        exit;
    }

    if (!$is_import_file && !ServiceProvider::isOffersImportEnabled($company_id)) {
        fn_echo('success');
        $logger->info(__('commerceml.controller.convert_offers_file_skiped_by_import_mode', ['[filename]' => $filename]));
        exit;
    }

    $file_path = sprintf('%s/%s', rtrim($upload_dir_path, '/'), $filename);

    fn_set_storage_data("commerceml_import_file_{$company_id}_{$filename}_{$session_id}", 'progress');
    fn_set_storage_data(
        "commerceml_import_file_timer_{$company_id}_{$filename}_{$session_id}",
        TIME + (SECONDS_IN_HOUR / 2)
    );

    $result = $command_bus->dispatch(
        CreateImportCommand::create([$file_path], $user_data, $import_key, ImportDto::IMPORT_TYPE_CATALOG, $company_id)
    );

    $logger->logResult($result);

    if (!$result->isSuccess()) {
        fn_echo('failure');
        fn_set_storage_data("commerceml_import_file_{$company_id}_{$filename}_{$session_id}", 'failure');
    } else {
        /** @var \Tygh\Addons\CommerceML\Dto\ImportDto $import */
        $import = $result->getData('import');

        if (!isset($session['converted_files'])) {
            $session['converted_files'] = [];
        }

        $session['import_id'] = $import->import_id;
        $session['converted_files'][$filename] = true;

        fn_echo('progress');
        fn_set_storage_data("commerceml_import_file_{$company_id}_{$filename}_{$session_id}", 'progress');
    }

    fn_set_storage_data("commerceml_import_file_timer_{$company_id}_{$filename}_{$session_id}");
    $logger->info(__('commerceml.controller.end_handle_catalog_import_convert', ['[filename]' => $filename]));
} elseif ($mode === 'catalog_import' && $filename && !empty($session['converted_files'][$filename])) {
    $session_id = $session->getID();
    fn_set_storage_data("commerceml_import_file_{$company_id}_{$filename}_{$session_id}");
    fn_set_storage_data("commerceml_import_file_timer_{$company_id}_{$filename}_{$session_id}");
    $import_id = (int) $session['import_id'];

    if ($action === 'analyze') {
        $logger->info(__('commerceml.controller.start_handle_catalog_import_analyze', ['[filename]' => $filename]));
        $command = RemoveImportCommand::create($import_id);
    } else {
        $logger->info(__('commerceml.controller.start_handle_catalog_import_execute', ['[filename]' => $filename]));
        $command = ExecuteCatalogImportCommand::create(
            $import_id,
            Registry::ifGet('config.tweaks.commerceml.catalog.time_limit', 25),
            ProductDto::REPRESENT_ENTITY_TYPE
        );
    }

    $result = $command_bus->dispatch($command);

    $logger->logResult($result);

    if ($result->isFailure()) {
        fn_echo('failure');

        if (!empty($params['is_manual'])) {
            unset($session['converted_files'], $session['import_id']);
        }
    } else {
        /** @var \Tygh\Addons\CommerceML\Dto\ImportDto $import */
        $import = $result->getData('import');

        if ($import->isStatusFinished()) {
            fn_echo('success');

            if (!empty($params['is_manual'])) {
                unset($session['converted_files'], $session['import_id']);
            }
        } else {
            fn_echo('progress');
        }
    }

    if ($action === 'analyze') {
        $logger->info(__('commerceml.controller.end_handle_catalog_import_analyze', ['[filename]' => $filename]));
    } else {
        $logger->info(__('commerceml.controller.end_handle_catalog_import_execute', ['[filename]' => $filename]));
    }
} elseif ($mode === 'sale_query') {
    if (!fn_check_permissions('orders', 'manage', 'admin', '', [], AREA, $user_id)) {
        $logger->info(__('commerceml.controller.access_denied'));

        fn_echo('failure');
        exit;
    }

    $logger->info(__('commerceml.controller.start_handle_export_orders'));

    $result = $command_bus->dispatch(
        ExportOrdersCommand::create($company_id, ServiceProvider::getImportSettings())
    );

    $logger->logResult($result);

    $xml_exported_orders = $result->getData('exported_orders');

    header('Content-type: text/xml; charset=utf-8');

    fn_echo("\xEF\xBB\xBF");
    fn_echo($xml_exported_orders);

    ExportOrdersCommand::updateLastExportTime($company_id);
    $logger->info(__('commerceml.controller.end_handle_export_orders'));
} elseif ($mode === 'sale_file') {
    if (
        !ServiceProvider::isImportChangesToOrderEnabled($company_id)
    ) {
        $logger->info(__('commerceml.controller.import_orders_is_disabled'));

        fn_echo('failure\n');
        fn_echo(__('commerceml.controller.import_orders_is_disabled'));
        exit;
    }
    if (
        !fn_check_permissions('orders', 'update_status', 'admin', '', [], AREA, $user_id)
        && !fn_check_permissions('order_management', 'edit', 'admin', '', [], AREA, $user_id)
    ) {
        $logger->info(__('commerceml.controller.import_orders_no_permissions'));

        fn_echo('failure\n');
        fn_echo(__('commerceml.controller.import_orders_no_permissions'));
        exit;
    }

    $logger->info(__('commerceml.controller.start_handle_catalog_file', ['[filename]' => $filename]));

    if (empty($filename)) {
        fn_echo('failure');
        $logger->error(__('commerceml.controller.error', ['[message]' => 'Filename is empty']));
        exit;
    }

    // some CRM don't call the sale_init step,
    // in this case we cleanup in this section
    if (!isset($session['old_files_cleanup'])) {
        $command_bus->dispatch(CleanUpFilesDirCommand::create($upload_dir_path));
        $session['old_files_cleanup'] = true;
    }

    $result = $command_bus->dispatch(
        UploadImportFileCommand::create($filename, $upload_dir_path)
    );

    $logger->info(__('commerceml.controller.end_handle_catalog_file', ['[filename]' => $filename]));

    if ($result->isSuccess()) {
        if (fn_strtolower(fn_get_file_ext($filename)) === 'zip') {
            $file_path = $result->getData('file_path', false);

            $logger->info(__('commerceml.controller.start_handle_catalog_import_unzip', ['[filename]' => basename($file_path)]));

            // validates archive and tries to unzip
            $result = $command_bus->dispatch(
                UnzipImportFileCommand::create($file_path, dirname($file_path), true, true, true)
            );

            if ($result->isSuccess()) {
                $logger->info(__('commerceml.controller.end_handle_catalog_import_unzip', ['[filename]' => basename($file_path)]));
            } else {
                fn_echo('success');
                exit;
            }
        }
    } else {
        fn_echo('failure');
        exit;
    }

    $file_list = $result->getData('file_list');
    if (!empty($file_list)) {
        $filename = reset($file_list);
    }

    $logger->info(__('commerceml.controller.start_handle_sale_import_convert', ['[filename]' => $filename]));

    $file_path = sprintf('%s/%s', rtrim($upload_dir_path, '/'), $filename);

    $result = $command_bus->dispatch(
        CreateImportCommand::create([$file_path], $user_data, $import_key, ImportDto::IMPORT_TYPE_ORDERS, $company_id)
    );

    $logger->logResult($result);

    if ($result->isFailure()) {
        fn_echo('failure');
        exit;
    }

    /** @var \Tygh\Addons\CommerceML\Dto\ImportDto $import */
    $import = $result->getData('import');
    $import_id = $import->import_id;

    $logger->info(__('commerceml.controller.start_handle_sale_import_execute', ['[filename]' => $filename]));

    $command = ExecuteSaleImportCommand::create(
        $import_id,
        0,
        OrderDto::REPRESENT_ENTITY_TYPE,
        fn_check_permissions('orders', 'update_status', 'admin', '', [], AREA, $user_id),
        fn_check_permissions('order_management', 'edit', 'admin', '', [], AREA, $user_id)
    );

    $result = $command_bus->dispatch($command);

    $logger->logResult($result);

    if ($result->isSuccess()) {
        fn_echo('success');
    } else {
        fn_echo('failure');
    }

    $logger->info(__('commerceml.controller.end_handle_sale_import_execute', ['[filename]' => $filename]));
} elseif ($mode === 'sale_success') {
    fn_echo('success');
} elseif ($mode === 'sale_import') {
    fn_echo("success\n");
    fn_echo("The order import file is now received and processed at this url:\n");
    fn_echo('your_site/commerceml?type=sale&mode=file&filename=your_filename');
}

exit;
