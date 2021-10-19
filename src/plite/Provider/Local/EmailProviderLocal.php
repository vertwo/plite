<?php
/**
 * Copyright (c) 2012-2021 Troy Wu
 * Copyright (c) 2021      Version2 OÜ
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */



namespace vertwo\plite\Provider\Local;



use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use function vertwo\plite\clog;



class EmailProviderLocal
{
    const DEBUG_DUMP_EMAIL = true;
    const DEBUG_SMTP       = false;



    private $shouldSendEmail = true;

    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;

    private $smtpFromEmail;
    private $smtpFromName;

    /** @var PHPMailer $mailer */
    private $mailer;



    function __construct ( $params )
    {
        $this->smtpHost      = $params['mail_host'];
        $this->smtpPort      = $params['mail_port'];
        $this->smtpUser      = $params['mail_user'];
        $this->smtpPass      = $params['mail_password'];
        $this->smtpFromEmail = $params['mail_from_email'];
        $this->smtpFromName  = $params['mail_from_name'];
    }



    /**
     * @param bool|array $params
     *
     * @throws Exception
     */
    function init ( $params = false )
    {
        $this->mailer = new PHPMailer(true);

        if ( self::DEBUG_SMTP ) $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;

        $this->mailer->isSMTP();
        $this->mailer->SMTPAuth   = true;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Host       = $this->smtpHost;
        $this->mailer->Port       = $this->smtpPort;
        $this->mailer->Username   = $this->smtpUser;
        $this->mailer->Password   = $this->smtpPass;

        $this->mailer->setFrom($this->smtpFromEmail, $this->smtpFromName);

        if ( array_key_exists("shouldSend", $params) )
            $this->shouldSendEmail = $params['shouldSend'];
    }



    /**
     * @param array $params - Map of email params
     *                      to-email,
     *                      to-name,
     *                      subject,
     *                      body
     *                      alt
     *
     * @return boolean - Was email sent?
     *
     * @throws Exception
     */
    public function sendEmail ( $params )
    {
        $toEmail = $params['to-email'];
        $toName  = $params['to-name'];
        $subject = $params['subject'];
        $body    = $params['body']; // HTML email
        $alt     = $params['alt'];  // plaintext email

        $this->mailer->clearAllRecipients();
        $this->mailer->addAddress($toEmail, $toName);
        $this->mailer->Subject = $subject;

        $body               = str_replace(LF, CRLF, $body);
        $this->mailer->Body = $body;

        if ( false !== $alt )
        {
            $alt                   = str_replace(LF, CRLF, $alt);
            $this->mailer->AltBody = $alt;
        }

        if ( self::DEBUG_DUMP_EMAIL ) clog("Prepping email for [ $toEmail ]...");

        if ( $this->shouldSendEmail )
        {
            $this->mailer->send();
            $didSend = true;
        }
        else
        {
            if ( self::DEBUG_DUMP_EMAIL ) clog("Prepared email", $alt);

            $didSend = false;
        }

        if ( self::DEBUG_DUMP_EMAIL ) clog("------------------------------------------------------");

        return $didSend;
    }
}
