<?php

namespace AnzeBlaBla\Simplite;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


class Mailer
{
    private static function send_mail($recipient_email, $recipient_name, $subject, $body, $alt_body = '')
    {
        $config = Application::getInstance()->getConfig('mail');
        
        $mail = new PHPMailer(true);

        try {
            //Server settings
            #$mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->isSMTP();
            $mail->Host       = $config['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['SMTP_USERNAME'];
            $mail->Password   = $config['SMTP_PASSWORD'];
            $mail->SMTPSecure = $config['SMTP_ENCRYPTION'];
            $mail->Port       = $config['SMTP_PORT'];

            //Recipients
            $mail->setFrom($config['SMTP_USERNAME'], $config['SMTP_FROM']);
            if ($recipient_name == null) {
                $mail->addAddress($recipient_email, $recipient_name);
            } else {
                $mail->addAddress($recipient_email);
            }
            #$mail->addReplyTo('info@example.com', 'Information');
            #$mail->addCC('cc@example.com');
            #$mail->addBCC('bcc@example.com');

            //Attachments
            #$mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
            #$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

            //Content
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $alt_body;

            $mail->send();
            return [
                'success' => true,
                'message' => 'Message has been sent'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $mail->ErrorInfo
            ];
        }
    }

    private static function render_email_template($template_name, $vars = [])
    {
        $config = Application::getInstance()->getConfig('mail');

        $template_path = $config['TEMPLATES_PATH'] . $template_name . '.php';

        if (!file_exists($template_path)) {
            die("Template $template_name not found");
        }

        extract($vars);
        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    public static function send_email_from_template($recipient_email, $recipient_name, $subject, $template_name, $vars = [])
    {
        $body = self::render_email_template($template_name, $vars);
        $alt_body = strip_tags($body);
        return self::send_mail($recipient_email, $recipient_name, $subject, $body, $alt_body);
    }
}
