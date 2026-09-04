<?php
/**
 * @package     Joomla.Site
 * @subpackage  Layout
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Uri\Uri;

/**
 * Custom HTML mail container for User Reminder emails.
 *
 * Configurable through User Reminder → Options → "Email Container":
 *   - ur_header : header title (falls back to the site name)
 *   - ur_logo   : inline logo cid (falls back to the header title)
 *   - ur_footer : footer HTML (falls back to the classic © line)
 *
 * When the email was not sent by User Reminder (no `ur_container` marker in
 * the extra layout data), this layout simply delegates to the core
 * joomla.mail.mailtemplate container so other extensions keep rendering
 * exactly as before.
 */

// Check if we have all the data
if (!array_key_exists('mail', $displayData)) {
    return;
}

$mailBody = $displayData['mail'];

if (!$mailBody) {
    return;
}

$extraData = [];

if (array_key_exists('extra', $displayData)) {
    $extraData = $displayData['extra'];
}

if (empty($extraData['ur_container'])) {
    // Not a User Reminder email — use Joomla's stock container.
    echo (new FileLayout('joomla.mail.mailtemplate', null, ['client' => 'site']))->render($displayData);

    return;
}

$siteUrl = Uri::root(false);
$lang    = $extraData['lang'] ?? 'en';

$header = isset($extraData['ur_header']) ? (string) $extraData['ur_header'] : '';
$footer = isset($extraData['ur_footer']) ? (string) $extraData['ur_footer'] : '';
$logo   = isset($extraData['ur_logo']) ? (string) $extraData['ur_logo'] : '';

$footerBg    = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($extraData['ur_footer_bg'] ?? '')) ? $extraData['ur_footer_bg'] : '#112855';
$footerColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($extraData['ur_footer_color'] ?? '')) ? $extraData['ur_footer_color'] : '#cccccc';

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES); ?>" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <meta name="x-apple-disable-message-reformatting">
        <!--[if !mso]><!-->
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <!--<![endif]-->
        <title></title>
        <!--[if mso]>
            <style>
                table {border-collapse:collapse;border-spacing:0;border:none;margin:0;}
                div, td {padding:0;}
                div {margin:0 !important;}
                </style>
            <noscript>
                <xml>
                <o:OfficeDocumentSettings>
                    <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings>
                </xml>
            </noscript>
            <![endif]-->
        <style>
            html {height: 100%;}
            table, td, div, h1, p { font-family: Arial, sans-serif; }
            .ur-footer, .ur-footer p {margin:0;font-size:12px;line-height:20px;color:<?php echo $footerColor; ?>;}
            .ur-footer a {color:<?php echo $footerColor; ?>;text-decoration:underline;}
        </style>
    </head>
    <body style="margin:0;padding:0;word-spacing:normal;background-color:#efefef;height:100%;">
        <div role="article" aria-roledescription="email" style="text-size-adjust:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;background-color:#efefef;height:100%;">
            <table role="presentation" style="width:100%;border:none;border-spacing:0;height:100%;">
                <tr>
                    <td align="center" style="vertical-align:baseline; padding:30px 0">
                        <!--[if mso]>
                        <table role="presentation" align="center" style="width:630px;">
                        <tr>
                        <td>
                        <![endif]-->
                        <table role="presentation" style="width:94%;max-width:630px;border:none;border-spacing:0;text-align:left;font-family:Arial,sans-serif;font-size:16px;line-height:22px;color:#363636;">
                            <tr>
                                <td style="padding:40px 30px 0 30px;text-align:center;font-size:24px;font-weight:bold;background-color:#ffffff;">
                                <?php if ($logo !== '' || $header !== '') : ?>
                                    <?php if ($logo !== '') : ?>
                                    <img src="cid:<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($header, ENT_QUOTES); ?> Logo" style="max-width:80%;height:auto;border:none;text-decoration:none;color:#ffffff;">
                                    <?php else : ?>
                                    <h1 style="margin-top:0;margin-bottom:0;font-size:26px;line-height:32px;font-weight:bold;letter-spacing:-0.02em;color:#112855;">
                                        <?php echo htmlspecialchars($header, ENT_QUOTES); ?>
                                    </h1>
                                    <?php endif; ?>
                                    <table style="width:100%;">
                                        <tr>
                                            <td style="border:15px solid #ffffff;"></td>
                                        </tr>
                                        <tr>
                                            <td style="border:1px solid #efefef;background-color:#efefef;padding:0;"></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:30px;background-color:#ffffff;">
                                <?php endif; ?>
                                    <?php echo $mailBody; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:30px;text-align:center;font-size:12px;background-color:<?php echo $footerBg; ?>;color:<?php echo $footerColor; ?>;">
                                    <div class="ur-footer"><?php echo $footer; ?></div>
                                </td>
                            </tr>
                        </table>
                        <!--[if mso]>
                        </td>
                        </tr>
                        </table>
                        <![endif]-->
                    </td>
                </tr>
            </table>
        </div>
    </body>
</html>
