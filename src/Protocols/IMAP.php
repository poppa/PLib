<?php
/**
 * IMAP classes
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.2
 * @package Protocols
 * @subpackage Mail
 * @uses PLibIterator
 * @depends {@link http://php.net/imap The PHP IMAP extension}
 */

if (!(defined('OP_READONLY'))) {
	throw new ImapException('No IMAP support compiled into this PHP ' .
	                        'installation');
}

/**
 * We need the iterator
*/
require_once PLIB_INSTALL_DIR . '/Core/Iterator.php';

/**
 * IMAP class. See {@see http://php.net/imap the IMAP pages at PHP.net}
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Protocols
 */
class IMAP
{
	/**
	 * The mailbox. Ex. {mail.domain.com:143}INBOX
	 * @var string
	*/
	protected $mailbox;
	/**
	 * The username
	 * @var string
	 */
	protected $username;
	/**
	 * The password
	 * @var string
	 */
	protected $password;
	/**
	 * Options. See {@see http://php.net/imap/ IMAP at php.net}
	 * @var int
	 */
	protected $options;
	/**
	 * Number of connections retries
	 * @var int
	 */
	protected $retries;
	/**
	 * The actual mailbox part of {@see IMAP::$mailbox}
	 * @var string
	 */
	protected $mbox;
	/**
	 * The actual host and port part of {@see IMAP::$mailbox}
	 * @var string
	 */
	protected $ref;
	/**
	 * The IMAP connection resource
	 * @var resource
	 */
	protected $resource;

	/**
	 * Constructor
	 *
	 * Takes the same params as {@see imap_open() imap_open}.
	 *
	 * @param string $mailbox
	 *   Ex: "{mail.domain.com:143}INBOX"
	 * @param string $username
	 * @param string $password
	 * @param int $flags
	 * @param int $retries
	 * @throws ImapException
	 */
	public function __construct($mailbox, $username, $password, $options=null,
	                            $retries=null)
	{
		$match = array();
		if (!preg_match('/\{(.*)(:.*)?\}(.*)?/', $mailbox, $match))
			throw new ImapException("Malformed \$mailbox parameter!");

		$this->ref      = "{{$match[1]}}";
		$this->mbox     = issetor($match[3], false);
		$this->mailbox  = $mailbox;
		$this->username = $username;
		$this->password = $password;
		$this->options  = $options;
		$this->retries  = $retries;

    // It's an assignment
		if (!($this->resource = imap_open($mailbox, $username, $password, $options,
		                                  $retries)))
		{
			throw new ImapException("Couldn't connect to mail server: " .
			                        imap_last_error());
		}
	}

	/**
	 * Fetches messages from $from to $to
	 *
	 * @link imap_fetch_overview()
	 * @param int $from
	 * @param int $to
	 * @param int $options
	 * @return MailIterator
	 */
	public function FetchOverview($from, $to, $options=null)
	{
		$res = imap_fetch_overview($this->resource, "$from:$to", $options);
		if (!is_array($res))
			$res = array();

		return new MailIterator($res);
	}

	/**
	 * Fetches the body structure of the message with number $msgno
	 *
	 * @link imap_fetchstructure()
	 * @param int $msgno
	 * @param int $options
	 * @return MailStructure
	 */
	public function FetchStructure($msgno, $options=null)
	{
		$struct = imap_fetchstructure($this->resource, $msgno, $options);
		return new MailStructure($struct, $msgno, $this);
	}

	/**
	 * Fetch the body
	 *
	 * @link imap_fetchbody()
	 * @param int $msgno
	 * @package int $partNumber
	 * @return string
	 */
	public function FetchBody($msgno, $partNumber)
	{
		return imap_fetchbody($this->resource, $msgno, $partNumber);
	}

	/**
	 * Returns the header for a given message
	 *
	 * @link imap_header()
	 * @param int $msgno
	 * @return stdClass
	 */
	public function Header($msgno)
	{
		return imap_header($this->resource, $msgno);
	}

	/**
	 * Takes an array from IMAP::Header['from'] and turns it into a string
	 *
	 * @param array $from
	 * @param bool $linkify
	 * @return string
	 */
	public static function HeaderArrayToString(array $from, $linkify=true)
	{
		$parts = array();

		foreach ($from as $a) {
			$tmp = null;
			if (isset($a->personal))
				$tmp = $a->personal . ' ';

			$email = $a->mailbox . '@' . $a->host;

			if ($linkify)
				$email = "<a href=\"mailto:$email\">$email</a>";

			if ($tmp)
				$email = "&lt;$email&gt;";

			$parts[] = $tmp . $email;
		}

		return IMAP::DecodeString(join(', ', $parts));
	}

	/**
	 * Turns a HTML formatted message into plain text
	 *
	 * @param string $text
	 * @param string $allowTags
	 * @return string
	 */
	public static function HTMLToPlainText($text, $allowTags=null)
	{
		$m = array();
		if (preg_match('#<body.[^>]*>(.*?)</body>#ims', $text, $m))
			$text = $m[1];

		$text = preg_replace('#<br.*?>#i', "\n", $text);
		$text = strip_tags($text, $allowTags);

		return $text;
	}

	/**
	 * Returns the text part of the message
	 *
	 * @param MailStructure $struct
	 * @param int $parts
	 * @param string $prefix
	 *   Used when recursing through a structure.
	 * @return array
	 *   The key is the subtype of the message, i.e. PLAIN or HTML
	 * @todo
	 *   Look into other types of text messages such as MESSAGE...
	 */
	public function GetText(MailStructure $struct, $type=MailStructure::PLAIN,
	                        $prefix=null)
	{
		$ret    = array();
		$iter   = $struct->GetIterator();
		$prefix = $prefix ? "$prefix." : null;

		if ($iter->HasNext()) {
			while ($iter->HasNext()) {
				$struct  = $iter->Next();
				$stype   = $struct->Type();
				$partnum = $prefix . $iter->Pointer();

				if (MailStructure::CheckFlag('MULTIPART', $stype))
					return $this->GetText($struct, $type, $partnum);

				if (MailStructure::CheckFlag('TEXT', $stype)) {
					$stype = MailStructure::$SUBTYPES[$struct->SubType()];

					if (check_bit_flag($type, $stype)) {
						$t = $this->parseStructure($struct, $partnum,
						                           MailStructure::TEXT|
						                           MailStructure::MESSAGE);
						if (!$t) continue;

						if (isset($ret[$struct->SubType()]))
							$ret[$struct->SubType()] .= $t;
						else
							$ret[$struct->SubType()] = $t;
					}
				}
			}
		}
		else {
			$ret[$struct->SubType()] =
				$this->parseStructure($struct, $prefix."1", MailStructure::TEXT);
		}

		return $ret;
	}

	/**
	 * Tries to find attachements if any
	 *
	 * @param MailStructure $struct
	 * @return array
	 */
	public function GetAttachments(MailStructure $struct, $prefix=null)
	{
		$iter   = $struct->GetIterator();
		$prefix = $prefix ? "$prefix." : null;
		$ret    = array();

		if ($iter->HasNext()) {
			while ($iter->HasNext()) {
				$struct  = $iter->Next();
				$type    = $struct->Type();
				//$stype   = $struct->SubType();
				$partnum = $prefix . $iter->Pointer();

				if (MailStructure::CheckFlag('MULTIPART', $type))
					$this->GetAttachments($struct, $partnum);

				$body = $this->parseStructure($struct, $partnum,
				                              MailStructure::IMAGE|
				                              MailStructure::APPLICATION|
				                              MailStructure::AUDIO|
				                              MailStructure::VIDEO);
				if ($body) {
					$name = $struct->GetDParameter('filename');
					if (!$name) $name = $struct->GetParameter('name');

					$ret[] = new MailAttachment(
						IMAP::DecodeString($name),
						$body,
						$struct->Disposition(),
						$struct->GetMimeType(),
						$struct->MessageNumber(),
						$partnum
					);
				}
			}
		}

		return $ret;
	}

	/**
	 * Pick out what ever...
	 *
	 * @param MailStructure $struct
	 * @param int $partNumber
	 * @param int $type
	 *  See MailStructure type constants
	 * @return mixed
	 */
	protected function parseStructure(MailStructure $struct, $partNumber, $type)
	{
		$msgno = $struct->MessageNumber();
		$body  = imap_fetchbody($this->resource, $msgno, $partNumber);
		$stype = $struct->Type();

		if (!(check_bit_flag($type, $stype)))
			return null;

		return $struct->DecodeBody($body);
	}

	/**
	 * Returns the message body of the message with number $msgno
	 *
	 * @link imap_body()
	 * @param int $msgno
	 * @param int $options
	 * * FT_UID      - The msg_number  is a UID
   * * FT_PEEK     - Do not set the \Seen flag if not already set
   * * FT_INTERNAL - The return string is in internal format, will not
   *                 canonicalize to CRLF.
	 * @return string
	 */
	public function Body($msgno, $options=null)
	{
		return imap_body($this->resource, $msgno, $options);
	}

	/**
	 * Reopen/open a mailbox
	 *
	 * @link imap_reopen()
	 * @param string $mailbox
	 * @return bool
	 */
	public function Reopen($mailbox)
	{
		return imap_reopen($this->resource, $mailbox);
	}

	/**
	 * Alias for {@see imap_check()}
	 *
	 * @return object
	 */
	public function Info()
	{
		return imap_check($this->resource);
	}

	/**
	 * Returns the number of messages in the current mailbox
	 *
	 * @link imap_num_msg()
	 * @return int
	 */
	public function Messages()
	{
		return imap_num_msg($this->resource);
	}

	/**
	 * Returns the number of recent messages in the current mailbox
	 *
	 * @link imap_num_recent()
	 * @return int
	 */
	public function RecentMessages()
	{
		return imap_num_recent($this->resource);
	}

	/**
	 * List the mailbox
	 *
	 * @link imap_list()
	 * @param string $pattern
	 * @return MailIterator
	 * @throws ImapException
	 */
	public function ListMailbox($pattern='*')
	{
    // It's an assigment
		if ($a = imap_list($this->resource, $this->mailbox, $pattern))
			return new MailIterator($a);

		throw new ImapException("Couldn't list mailbox: " . imap_last_error());
	}

	/**
	 * Return all mailboxes matching pattern $pattern
	 *
	 * @link imap_getmailboxes()
	 * @param string $pattern
	 * @return MailIterator
	 * @throws ImapException
	 */
	public function GetMailboxes($pattern)
	{
    // It's an assigment
		if ($a = imap_getmailboxes($this->resource, $this->ref, $pattern))
			return new MailIterator($a);

		throw new ImapException("Couldn't get mailboxes: " . imap_last_error());
	}

	/**
	 * Decodes a quoted printable encoded string
	 *
	 * @link iconv_mime_decode()
	 * @param string $str
	 * @return string
	 */
	public static function DecodeString($str)
	{
		$nstr = null;
    // It's an assigment
		if (!($nstr = @iconv_mime_decode($str)))
			$nstr = $str;

		return $nstr;
	}

	/**
	 * Returns status information on a mailbox
	 *
	 * @link imap_status()
	 * @param string $mailbox
	 *   Check status on mailbox other than the default set in the constructor
	 * @param int $options
	 * * SA_MESSAGES    - set status->messages to the number of messages in the
	 *                    mailbox
   * * SA_RECENT      - set status->recent to the number of recent messages
   *                    in the mailbox
   * * SA_UNSEEN      - set status->unseen to the number of unseen (new)
   *                    messages in the mailbox
   * * SA_UIDNEXT     - set status->uidnext to the next uid to be used in the
   *                    mailbox
   * * SA_UIDVALIDITY - set status->uidvalidity to a constant that changes
   *                    when uids for the mailbox may no longer be valid
   * * SA_ALL         - set all of the above
   *
	 * @return object
	 */
	public function Status($mailbox=null, $options=SA_ALL)
	{
		$mb = issetor($mailbox, $this->mailbox);

		if (!$mb)
			throw new ImapException("No mailbox selected to check status on!");

		return imap_status($this->resource, $mb, $options);
	}

	/**
	 * This function performs a search on the mailbox currently opened in the
	 * given imap stream.
	 *
	 * @link imap_search()
	 * @param string $criteria
	 * @param int $options
	 * A string, delimited by spaces, in which the following keywords are
   * allowed. Any multi-word arguments (e.g. FROM "joey smith") must be
   * quoted.
   *
   *   * ALL                - return all messages matching the rest of the
   *                          criteria
   *   * ANSWERED           - match messages with the \\ANSWERED flag set
   *   * BCC "string"       - match messages with "string" in the Bcc: field
   *   * BEFORE "date"      - match messages with Date: before "date"
   *   * BODY "string"      - match messages with "string" in the body of the
   *                          message
   *   * CC "string"        - match messages with "string" in the Cc: field
   *   * DELETED            - match deleted messages
   *   * FLAGGED            - match messages with the \\FLAGGED (sometimes
   *                          referred to as Important or Urgent) flag set
   *   * FROM "string"      - match messages with "string" in the From: field
   *   * KEYWORD "string"   - match messages with "string" as a keyword
   *   * NEW                - match new messages
   *   * OLD                - match old messages
   *   * ON "date"          - match messages with Date: matching "date"
   *   * RECENT             - match messages with the \\RECENT flag set
   *   * SEEN               - match messages that have been read (the \\SEEN
   *                          flag is set)
   *   * SINCE "date"       - match messages with Date: after "date"
   *   * SUBJECT "string"   - match messages with "string" in the Subject:
   *   * TEXT "string"      - match messages with text "string"
   *   * TO "string"        - match messages with "string" in the To:
   *   * UNANSWERED         - match messages that have not been answered
   *   * UNDELETED          - match messages that are not deleted
   *   * UNFLAGGED          - match messages that are not flagged
   *   * UNKEYWORD "string" - match messages that do not have the keyword
   *                          "string"
   *   * UNSEEN             - match messages which have not been read yet
   *
	 * @param string $charset
	 * @return MailIterator
	 */
	public function Search($criteria, $options=null, $charset=null)
	{
		$a = imap_search($this->resource, $criteria, $options, $charset);
		if (!is_array($a))
			$a = array();

		return new MailIterator($a);
	}

	/**
	 * Close the connection to the IMAP server
	 * @link imap_close()
	 */
	public function Close()
	{
		if (is_resource($this->resource))
			imap_close($this->resource);
	}

	/**
	 * Destructor. Alias of {@see IMAP::Close()}
	 */
	public function __destruct()
	{
		$this->Close();
	}
}
/**
 * A representation of an attachment
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Protocols
 * @subpackage Mail
 */
class MailAttachment
{
	/**
	 * The mimetype
	 * @var string
	 */
	protected $mimetype;
	/**
	 * The file name
	 * @var string
	 */
	protected $filename;
	/**
	 * The actual content
	 * @var string
	 */
	protected $data;
	/**
	 * Content disposition (INLINE/ATTACHMENT)
	 * @var string
	 */
	protected $disposition;
	/**
	 * The size of the data
	 * @var int
	 */
	protected $size = 0;
	/**
	 * The message this attachment belongs to
	 * @var int
	 */
	protected $msgno = 0;
	/**
	 * The part in the messages the attachment is at
	 * @var string|int
	 */
	protected $partnum  = 0;

	/**
	 * Constructor
	 *
	 * @param string $filename
	 * @param string $data
	 * @param string $dispoition
	 * @param string $mimetype
	 * @param int $msgno
	 *  The message number of the message the attachment belongs to
	 * @param int|string $partnum
	 *  The part in the body structure the attachment belongs to
	 */
	public function __construct($filename, $data, $dispoition, $mimetype,
	                            $msgno, $partnum)
	{
		$this->filename    = $filename;
		$this->data        = $data;
		$this->disposition = $dispoition;
		$this->mimetype    = $mimetype;
		$this->size        = strlen($data);
		$this->msgno       = $msgno;
		$this->partnum     = $partnum;
	}

	/**
	 * Setter/getter for the mimetype
	 *
	 * @param string $set
	 * @return string|void
	 */
	public function MimeType($set=null)
	{
		if (!$set)
			return $this->mimetype;

		$this->mimetype = $set;
	}

	/**
	 * Setter/getter for the filename
	 *
	 * @param string $set
	 * @return string|void
	 */
	public function Filename($set=null)
	{
		if (!$set)
			return $this->filename;

		$this->filename = $set;
	}

	/**
	 * Setter/getter for the content disposition
	 *
	 * @param string $set
	 * @return string|void
	 */
	public function Disposition($set=null)
	{
		if (!$set)
			return $this->disposition;

		$this->disposition = $set;
	}

	/**
	 * Setter/getter for the file data
	 *
	 * @param string $set
	 * @return string|void
	 */
	public function Data($set=null)
	{
		if (!$set)
			return $this->data;

		$this->data = $set;
		$this->size = strlen($set);
	}

	/**
	 * Returns the size of the data
	 *
	 * @return int
	 */
	public function Size()
	{
		return $this->size;
	}

	/**
	 * Saves the file to disk
	 *
	 * @param string $path
	 * @param bool $owerwrite
	 * @param int $mode
	 * @throws Exception
	 * @return string
	 *   The path to the file
	 */
	public function SaveToDisk($path, $owerwrite=false, $mode=0766)
	{
		$tmpfilename = "{$this->msgno}_{$this->partnum}_{$this->filename}";
		$f = rtrim($path, '/') . "/" . urldecode($tmpfilename);

		if (file_exists($f) && !$owerwrite)
			return $f;

		if (!is_writable($path))
			throw new Exception("The path \"$path\" is not writable!");

		$fh = fopen($f, "wb");
		fwrite($fh, $this->data);
		fclose($fh);

		chmod($f, $mode);

		return $f;
	}
} // Attachment

/**
 * Representation of a structure object returned from
 * {@see imap_fetchstructure()} or {@see IMAP::FetchStructure()}
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Protocols
 * @subpackage Mail
 */
class MailStructure
{
	//! Primary body types
	const TEXT         =   1;
	const MULTIPART    =   2;
	const MESSAGE      =   4;
	const APPLICATION  =   8;
	const AUDIO        =  16;
	const IMAGE        =  32;
	const VIDEO        =  64;
	const OTHER        = 128;

	//! Subtypes
	const PLAIN        =   1;
	const HTML         =   2;
	const MIXED        =   4;
	const OCTET_STREAM =   8;
	const RFC822       =  16;
	const ALTERNATIVE  =  32;
	const RICHTEXT     =  64;
	const GIF          = 128;
	const JPEG         = 256;
	const PNG          = 512;

	/**
	 * Subtypes
	 * @var array
	 */
	static $SUBTYPES = array(
		'PLAIN'        => MailStructure::PLAIN,
		'HTML'         => MailStructure::HTML,
		'MIXED'        => MailStructure::MIXED,
		'OCTET-STREAM' => MailStructure::OCTET_STREAM,
		'RFC822'       => MailStructure::RFC822,
		'ALTERNATIVE'  => MailStructure::ALTERNATIVE,
		'RICHTEXT'     => MailStructure::RICHTEXT,
		'GIF'          => MailStructure::GIF,
		'JPEG'         => MailStructure::JPEG,
		'PNG'          => MailStructure::PNG
	);
	/**
	 * Primary body types
	 * @var array
	 */
	static $TYPES = array(
		'TEXT'        => MailStructure::TEXT,
		'MULTIPART'   => MailStructure::MULTIPART,
		'MESSAGE'     => MailStructure::MESSAGE,
		'APPLICATION' => MailStructure::APPLICATION,
		'AUDIO'       => MailStructure::AUDIO,
		'IMAGE'       => MailStructure::IMAGE,
		'VIDEO'       => MailStructure::VIDEO,
		'OTHER'       => MailStructure::OTHER,
	);
	/**
	 * Transfer encodings
	 * @var array
	 */
	static $TRANSFER_ENCODINGS = array(
		'7BIT',
		'8BIT',
		'BINARY',
		'BASE64',
		'QUOTED-PRINTABLE',
		'OTHER'
	);
	/**
	 * The message number
	 * @var int
	 */
	protected $msgno;
	/**
	 * Structure object returned from {@see imap_fetchstructure()} or
	 * {@see IMAP::FetchStructure()}
	 * @var object
	 */
	protected $struct;

	/**
	 * Constructor
	 *
	 * @param stdClass $struct
	 * @param int $msgno
	 */
	public function __construct(stdClass $struct, $msgno)
	{
		$this->struct = $struct;
		$this->msgno  = $msgno;
	}

	/**
	 * Returns the mimetype of the structure
	 *
	 * @return string
	 */
	public function GetMimeType()
	{
		$_1 = array_search($this->Type(), self::$TYPES);
		$_2 = $this->SubType();
		return strtolower("$_1/$_2");
	}

	/**
	 * Check a flag against the main types
	 *
	 * <code>
	 * if (MailStructure::CheckFlag('IMAGE', $struct->GetType()))
	 *   echo "It's an image!";
	 * </code>
	 *
	 * @param string $which
	 * @param int $what
	 * @return bool
	 */
	public static function CheckFlag($which, $what)
	{
		$flag = issetor(self::$TYPES[$which], -1);
		return ($what & $flag) == $flag;
	}

	/**
	 * Returns the structure object
	 *
	 * @return Object
	 */
	public function GetStructureObject()
	{
		return $this->struct;
	}

	/**
	 * Return the number of parts in the message
	 *
	 * @return int
	 */
	public function NumParts()
	{
		return isset($this->struct->parts) ? sizeof($this->struct->parts) : 0;
	}

	/**
	 * Return the parts
	 *
	 * @return array
	 */
	public function Parts()
	{
		return $this->NumParts() > 0 ? $this->struct->parts : array();
	}

	/**
	 * Returns the message�s transfer encoding
	 *
	 * @return string
	 */
	public function Encoding()
	{
		return self::$TRANSFER_ENCODINGS[$this->struct->encoding];
	}

	/**
	 * Returns the body type of the message
	 *
	 * @param bool $bitwise
	 *  Since most type checking is done by bit wise comparison and the types
	 *  (in the actual IMAP structure) we in most cases want the BIT
	 *  representation of the type. But if you want the actual type number
	 *  set this to false.
	 * @return int
	 */
	public function Type($bitwise=true)
	{
		if ($bitwise)
			return pow(2, $this->struct->type);

		return $this->struct->type;
	}

	/**
	 * Returns if the messages has an subtype or not
	 *
	 * @return bool
	 */
	public function HasSubType()
	{
		return $this->struct->ifsubtype == 0 ? false : true;
	}

	/**
	 * Returns the subtype if any
	 *
	 * @return string
	 */
	public function SubType()
	{
		if ($this->HasSubType())
			return $this->struct->subtype;

		return null;
	}

	/**
	 * Returns whether or not the message has an description
	 *
	 * @return bool
	 */
	public function HasDescription()
	{
		return $this->struct->ifdescription == 0 ? false : true;
	}

	/**
	 * Returns the description if any
	 *
	 * @return string
	 */
	public function Description()
	{
		if ($this->HasDescription())
			return $this->struct->description;

		return null;
	}

	/**
	 * Returns whether or not the message has an disposition
	 *
	 * @return bool
	 */
	public function HasDisposition()
	{
		return $this->struct->ifdisposition == 0 ? false : true;
	}

	/**
	 * Returns the message�s disposition if any.
	 *
	 * @return string
	 */
	public function Disposition()
	{
		if ($this->HasDisposition())
			return $this->struct->disposition;

		return null;
	}

	/**
	 * Returns whether or not the message has an id
	 *
	 * @return bool
	 */
	public function HasId()
	{
		return $this->struct->ifid == 0 ? false : true;
	}

	/**
	 * Returns the message�s id if any.
	 *
	 * @return string
	 */
	public function Id()
	{
		if ($this->HasId())
			return $this->struct->id;

		return null;
	}

	/**
	 * Returns whether or not the message has dparameters or not
	 *
	 * @return bool
	 */
	public function HasDParameters()
	{
		return $this->struct->ifdparameters == 0 ? false : true;
	}

	/**
	 * Returns the message�s dparameters if any.
	 *
	 * @return array
	 */
	public function DParameters()
	{
		if ($this->HasDParameters())
			return $this->struct->dparameters;

		return array();
	}

	/**
	 * Returns whether or not the message has parameters or not
	 *
	 * @return bool
	 */
	public function HasParameters()
	{
		return $this->struct->ifparameters == 0 ? false : true;
	}

	/**
	 * Returns the message�s dparameters if any.
	 *
	 * @return array
	 */
	public function Parameters()
	{
		if ($this->HasParameters())
			return $this->struct->parameters;

		return array();
	}

	/**
	 * Get a specific paramter from $this->struct->parameters
	 *
	 * @param string $which
	 * @return string
	 */
	public function GetParameter($which)
	{
		$which = strtolower($which);
		$p = $this->Parameters();

		foreach ($p as $param) {
			if (strtolower($param->attribute) == $which)
				return $param->value;
		}

		return null;
	}

	/**
	 * Get a specific paramter from $this->struct->dparameters
	 *
	 * @param string $which
	 * @return string
	 */
	public function GetDParameter($which)
	{
		$which = strtolower($which);
		$p = $this->DParameters();

		foreach ($p as $param) {
			if (strtolower($param->attribute) == $which)
				return $param->value;
		}

		return null;
	}

	/**
	 * Return the number of lines
	 *
	 * @return int
	 */
	public function Lines()
	{
		return $this->struct->lines;
	}

	/**
	 * Return the number of bytes
	 *
	 * @return int
	 */
	public function Bytes()
	{
		return $this->struct->bytes;
	}

	/**
	 * Returns an iterator for the message parts
	 *
	 * @return StructIterator
	 */
	public function GetIterator()
	{
		return new StructIterator($this->Parts(), $this->msgno);
	}

	/**
	 * Returns the message number
	 *
	 * @return int
	 */
	public function MessageNumber()
	{
		return $this->msgno;
	}

	/**
	 * Decodes the body part of the message associated with this structure
	 *
	 * @param string $body
	 * @return string
	 */
	public function DecodeBody($body)
	{
		switch ($this->Encoding()) {
			case '7BIT':
				$charset = issetor($this->GetParameter('charset'), 'utf-7');
				$tbody = $body;
        // It's an assigment
				if (($body = @iconv($charset, 'iso88591', $body)) === false)
					$body = $tbody;

				break;

			case 'BASE64':
				$body = base64_decode($body);
				break;

			case 'QUOTED-PRINTABLE':
				$body = quoted_printable_decode($body);
				break;

			case 'BINARY':
				wbr("BINARY! What to do?");
				break;

			case '8BIT':
				$charset = $this->GetParameter('charset');
				if ($charset && strtolower($charset) == 'utf-8')
					$body = @utf8_decode($body);
				//$charset = issetor($this->GetParameter('charset', 'utf8'));
				//$body = iconv($charset, 'iso88591', $body);
				break;
		}

		return $body;
	}
} // MailStructure

/**
 * A generic iterator
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @package Protocols
 * @subpackage Mail
 * @depends PLibIterator
 */
class MailIterator extends PLibIterator
{
	/**
	 * Constructor
	 *
	 * @param array $container
	 */
	public function __construct(array $container)
	{
		$this->container = $container;
		$this->length = sizeof($container);
	}

	/**
	 * Is there a next item in the iterator?
	 *
	 * @return bool
	 */
	public function HasNext()
	{
		return array_key_exists($this->pointer, $this->container);
	}

	/**
	 * Returns the next item in the iterator
	 *
	 * @return mixed
	 */
	public function Next()
	{
		return $this->container[$this->pointer++];
	}

	/**
	 * Sorting function.
	 *
	 * @since 0.2
	 * @param string $key
	 *   The key to sort on. If you want the result sorted in descending order
	 *   prefix the key with a -. Like  `$iter->Sort('-date')`.
	 */
	public function Sort($key)
	{
		$order = SORT_ASC;
		if ($key[0] == '-') {
			$order = SORT_DESC;
			$key = substr($key, 1);
		}

		$f = create_function('$a,$b', "
			if (isset(\$a->{$key}) && isset(\$b->{$key})) {
				if (\"$key\" == 'date') {
					\$a = strtotime(\$a->{$key});
					\$b = strtotime(\$b->{$key});
					return \$a != \$b ? \$a > \$b ? 1 : -1 : 0;
				}
				return strcmp(\$a->{$key}, \$b->{$key});
			}
			return 0;"
		);

		uasort($this->container, $f);

		if ($order == SORT_DESC)
			$this->container = array_reverse($this->container);
	}
}

/**
 * An Iterator for the parts in a mail structure
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Protocols
 * @subpackage Mail
 * @depends PLibIterator
 */
class StructIterator extends MailIterator
{
	/**
	 * Constructor
	 *
	 * @param array $parts
	 */
	public function __construct(array $parts, $msgno)
	{
		foreach ($parts as $part)
			$this->container[] = new MailStructure($part, $msgno);

		$this->length = sizeof($this->container);
	}

	/**
	 * Returns the next object in the iterator
	 *
	 * @return MailStructure
	 */
	public function Next()
	{
		return $this->container[$this->pointer++];
	}
}

/**
 * Generic IMAP exception
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Protocols
 * @subpackage Exception
 */
class ImapException extends Exception
{
	public $message = "An IMAP error occured!";
}
?>