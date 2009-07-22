<?php
/**
 * ID3 tag reader
 *
 * @author Pontus Östlund <spam@poppa.se>
 * @version 0.1
 * @package Media
 * @uses StreamReader
 * @uses StringReader
 * @uses File
 * @uses Version
 */

/**
 * This file requires the {@link StremReader} class
 */
require_once PLIB_INSTALL_DIR . '/IO/StreamReader.php';
/**
 * This file requires the {@link StringReader} class
 */
require_once PLIB_INSTALL_DIR . '/String/String.php';
/**
 * This file requires the {@link File} class
 */
require_once PLIB_INSTALL_DIR . '/IO/StdIO.php';
/**
 * Need the {@link Version} class
 */
require_once PLIB_INSTALL_DIR . '/Core/Version.php';

/**
 * Base class of media file tags (ID3...)
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Media
 */
abstract class TagLib
{
	/**
	 * No tag type
	 * @var int
	 */
	const TYPE_NONE = 0;
	/**
	 * ID3 version 1 tag
	 * @var int
	 */
	const TYPE_ID3V1 = 2;
	/**
	 * ID3 version 2 tag
	 * @var int
	 */
	const TYPE_ID3V2 = 4;

	/**
	 * Type of tag
	 * @var int
	 */
	public $type = self::TYPE_NONE;
	/**
	 * ID3 genre list. Genres beyond 79 are Winamp extensions
	 * @var array
	 */
	public static $GENRES = array (
		0 => 'Blues', 1 => 'Classic Rock', 2 => 'Country', 3 => 'Dance',
		4 => 'Disco', 5 => 'Funk', 6 => 'Grunge', 7 => 'Hip-Hop', 8 => 'Jazz',
		9 => 'Metal', 10 => 'New Age', 11 => 'Oldies', 12 => 'Other', 13 => 'Pop',
		14 => 'R&B', 15 => 'Rap', 16 => 'Reggae', 17 => 'Rock', 18 => 'Techno',
		19 => 'Industrial', 20 => 'Alternative', 21 => 'Ska', 22 => 'Death Metal',
		23 => 'Pranks', 24 => 'Soundtrack', 25 => 'Euro-Techno', 26 => 'Ambient',
		27 => 'Trip-Hop', 28 => 'Vocal', 29 => 'Jazz+Funk', 30 => 'Fusion',
		31 => 'Trance', 32 => 'Classical', 33 => 'Instrumental', 34 => 'Acid',
		35 => 'House', 36 => 'Game', 37 => 'Sound Clip', 38 => 'Gospel',
		39 => 'Noise', 40 => 'AlternRock', 41 => 'Bass', 42 => 'Soul',
		43 => 'Punk', 44 => 'Space', 45 => 'Meditative', 46 => 'Instrumental Pop',
		47 => 'Instrumental Rock', 48 => 'Ethnic', 49 => 'Gothic', 50 => 'Darkwave',
		51 => 'Techno-Industrial', 52 => 'Electronic', 53 => 'Pop-Folk',
		54 => 'Eurodance', 55 => 'Dream', 56 => 'Southern Rock', 57 => 'Comedy',
		58 => 'Cult', 59 => 'Gangsta', 60 => 'Top 40', 61 => 'Christian Rap',
		62 => 'Pop/Funk', 63 => 'Jungle', 64 => 'Native American', 65 => 'Cabaret',
		66 => 'New Wave', 67 => 'Psychadelic', 68 => 'Rave', 69 => 'Showtunes',
		70 => 'Trailer', 71 => 'Lo-Fi', 72 => 'Tribal', 73 => 'Acid Punk',
		74 => 'Acid Jazz', 75 => 'Polka', 76 => 'Retro', 77 => 'Musical',
		78 => 'Rock & Roll', 79 => 'Hard Rock',
		// Winamp extension
		80 => 'Folk', 81 => 'Folk-Rock', 82 => 'National Folk', 83 => 'Swing',
		84 => 'Fast Fusion', 85 => 'Bebob', 86 => 'Latin', 87 => 'Revival',
		88 => 'Celtic', 89 => 'Bluegrass', 90 => 'Avantgarde', 91 => 'Gothic Rock',
		92 => 'Progressive Rock', 93 => 'Psychedelic Rock', 94 => 'Symphonic Rock',
		95 => 'Slow Rock', 96 => 'Big Band', 97 => 'Chorus', 98 => 'Easy Listening',
		99 => 'Acoustic', 100 => 'Humour', 101 => 'Speech', 102 => 'Chanson',
		103 => 'Opera', 104 => 'Chamber Music', 105 => 'Sonata', 106 => 'Symphony',
		107 => 'Booty Bass', 108 => 'Primus', 109 => 'Porn Groove',
		110 => 'Satire', 111 => 'Slow Jam', 112 => 'Club', 113 => 'Tango',
		114 => 'Samba', 115 => 'Folklore', 116 => 'Ballad', 117 => 'Power Ballad',
		118 => 'Rhythmic Soul', 119 => 'Freestyle', 120 => 'Duet',
		121 => 'Punk Rock', 122 => 'Drum Solo', 123 => 'A capella',
		124 => 'Euro-House', 125 => 'Dance Hall'
	);

	/**
	 * Returns the string reprentation of genre with id `$id`
	 *
	 * @param int $id
	 * @return string
	 */
	public static function GetGenreById($id)
	{
		if (array_key_exists($id, self::$GENRES))
			return self::$GENRES[$id];

		return null;
	}

	/**
	 * Returns the ID of `$genre`
	 *
	 * @param string $genre
	 * @return int
	 */
	public static function GetIdOfGenre($genre)
	{
		return array_search($genre, self::$GENRES);
	}

	/**
	 * Perform "unsynchronization" on the data.  This takes things that
	 * look like mpeg syncs, 11111111 111xxxxx, and stuffs a zero
	 * in between the bytes.  In addition, it stuffs a zero after every
	 * ff 00 combination.
	 *
	 * Note that this is A.F.U., because the standard doesn't say to stuff
	 * an extra zero in 11111111 00000000 111xxxxx.  It says to stuff it
	 * after 11111111 00000000.  That's broken.  Seems to be what id3lib
	 * does also, though.
	 *
	 * This is a port of the same method in the PERL module.
	 *
	 * @param string $data
	 * @return string
	 */
	protected function unsyncronize($data)
	{
		$data = preg_replace('/\xff\0/', '\xff\0\0', $data);
		$data = preg_replace('/\xff(?=[\xe0-\xff])/', '\xff\x00', $data);
		return $data;
	}

	/**
	 * The oposit of {@see TagLib::unsynchronize()}.
	 *
	 * @param string $data
	 * @return string
	 */
	protected function unUnsynchronize($data)
	{
		$data = preg_replace('/\xff\x00([\xe0-\xff])/', '\xff$1', $data);
		$data = preg_replace('/\xff\x00\x00/', '\xff\x00', $data);
		return $data;
	}

	/**
	 * Takes an ID3 size field and returns an integer
	 *
	 * @param int $size
	 * @return int
 	 */
	protected function unmungeSize($size)
	{
		$newsize = 0;

		for ($pos = 0; $pos < 4; $pos++) {
			$mask = 0xff << ($pos * 8);
			$val  = ($size & $mask) >> ($pos * 8);
			$newsize |= $val << ($pos * 7);
		}

		return $newsize;
	}

	/**
	 * Takes an integer and returns an ID3 size field
	 *
	 * @param int $size
	 * @return int
	 */
	protected function mungeSize($size)
	{
		$newsize = 0;

		for ($pos = 0; $pos < 4; $pos++) {
			$val = ($size >> ($pos * 7)) & 0x7f;
			$newsize |= $val << ($pos * 8);
		}

		return $newsize;
	}
}

/**
 * Base class for ID3 tags
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Media
 */
class ID3 extends TagLib
{
	/**
	 * Title
	 * @var string
	 */
	protected $title = null;
	/**
	 * Album title
	 * @var string
	 */
	protected $album = null;
	/**
	 * Artist
	 * @var string
	 */
	protected $artist = null;
	/**
	 * Year
	 * @var int
	 */
	protected $year = 0;
	/**
	 * Comment
	 * @var string
	 */
	protected $comment = null;
	/**
	 * The track number
	 * @var int
	 */
	protected $track = null;
	/**
	 * Genre
	 * @var int
	 */
	protected $genre = null;
	/**
	 * File identifier
	 * @var string
	 */
	protected $fileIdentifier = 'TAG';
	/**
	 * Stream reader
	 * @var StreamReader
	 */
	protected $stream = null;
	/**
	 * Head, first 10 bytes
	 * @var string
	 */
	protected $head = null;
	/**
	 * The ID3 tag
	 * @var string
	 */
	protected $tag  = null;
	/**
	 * The real file
	 * @var File
	 */
	protected $file = null;
	/**
	 * The version
	 * @var Version
	 */
	protected $version = null;

	/**
	 * Hidden constructor.
	 * This class must be instantiated from a derived class.
	 *
	 * @param File $file
	 * @param array $head
	 */
	protected function __construct(File $file, array $head)
	{
		$this->head    = $head;
		$this->file    = $file;
		$this->stream  = new StreamReader($file);
		$this->version = new Version($head['majorver'], $head['minorver']);
	}

	/**
	 * Returns the song title
	 *
	 * @return string
	 */
	public function Title()
	{
		return $this->title;
	}

	/**
	 * Returns the artist
	 *
	 * @return string
	 */
	public function Artist()
	{
		return $this->artist;
	}

	/**
	 * Returns the album name
	 *
	 * @return string
	 */
	public function Album()
	{
		return $this->album;
	}

	/**
	 * Returns the year of the album/song
	 *
	 * @return int
	 */
	public function Year()
	{
		return $this->year;
	}

	/**
	 * Returns the comment
	 *
	 * @return string
	 */
	public function Comment()
	{
		return $this->comment;
	}

	/**
	 * Returns the track number
	 *
	 * @return int
	 */
	public function Track()
	{
		return $this->track;
	}

	/**
	 * Returns the genre.
	 *
	 * @param bool $asInt
	 *  If set the integer value of the genre will be returned
	 * @return int|string
	 */
	public function Genre($asInt=false)
	{
		if ($this->genre) {
			if($asInt) return $this->genre;
			return self::GetGenreById($this->genre);
		}

		return null;
	}

	/**
	 * Factory method for reading ID3 information from a media file.
	 *
	 * @throws ID3Exception
	 *  If no tag is found or the tag is unsupported
	 * @param string|File $file
	 * @return ID3v1|ID3v2
	 */
	public final static function Create($file)
	{
		if (!($file instanceof File)) {
			try { $file = new File($file); }
			catch (Exception $e) {
				error_log($e->getMessage());
				return 0;
			}
		}

		$sr   = new StreamReader($file->path);
		$head = $sr->Read(10);
		$head = unpack('a3tag/Cmajorver/Cminorver/Cflags/Nsize', $head);

		switch ($head['tag'])
		{
			case 'ID3':
				$sr->Close();

				if ($head['majorver'] >= 3)
					return new ID3v2($file, $head);
				else
					return new ID3v1($file, $head);

				throw new ID3Exception('Unknown ID3 major version: '.$head['majorver']);
				break;

			default:
				$h2 = $sr->ReadBlock($file->size-128, 128);
				$sr->Close();

				if (String::StartsWith($h2, 'TAG'))
					return new ID3v1($file, $head);

				throw new ID3Exception("Unhandled tag type: {$head['tag']}");
		}
	}

	/**
	 * Destructor
	 */
	public function __destruct()
	{
		$this->stream->Close();
	}
}

/**
 * ID3v2 class.
 * Don't instantiate this class directly. Use {@see ID3::Create()} instead.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
 */
class ID3v2 extends ID3
{
	public $type = self::TYPE_ID3V2;

	/**
	 * Available frame id's
	 * @var array
	 */
	private $frameids = array(
		'AENC', // [#sec4.20 Audio encryption]
		'APIC', // [#sec4.15 Attached picture]
		'COMM', // [#sec4.11 Comments]
		'COMR', // [#sec4.25 Commercial frame]
		'ENCR', // [#sec4.26 Encryption method registration]
		'EQUA', // [#sec4.13 Equalization]
		'ETCO', // [#sec4.6 Event timing codes]
		'GEOB', // [#sec4.16 General encapsulated object]
		'GRID', // [#sec4.27 Group identification registration]
		'IPLS', // [#sec4.4 Involved people list]
		'LINK', // [#sec4.21 Linked information]
		'MCDI', // [#sec4.5 Music CD identifier]
		'MLLT', // [#sec4.7 MPEG location lookup table]
		'OWNE', // [#sec4.24 Ownership frame]
		'PRIV', // [#sec4.28 Private frame]
		'PCNT', // [#sec4.17 Play counter]
		'POPM', // [#sec4.18 Popularimeter]
		'POSS', // [#sec4.22 Position synchronisation frame]
		'RBUF', // [#sec4.19 Recommended buffer size]
		'RVAD', // [#sec4.12 Relative volume adjustment]
		'RVRB', // [#sec4.14 Reverb]
		'SYLT', // [#sec4.10 Synchronized lyric/text]
		'SYTC', // [#sec4.8 Synchronized tempo codes]
		'TALB', // [#TALB Album/Movie/Show title]
		'TBPM', // [#TBPM BPM (beats per minute)]
		'TCOM', // [#TCOM Composer]
		'TCON', // [#TCON Content type]
		'TCOP', // [#TCOP Copyright message]
		'TDAT', // [#TDAT Date]
		'TDLY', // [#TDLY Playlist delay]
		'TENC', // [#TENC Encoded by]
		'TEXT', // [#TEXT Lyricist/Text writer]
		'TFLT', // [#TFLT File type]
		'TIME', // [#TIME Time]
		'TIT1', // [#TIT1 Content group description]
		'TIT2', // [#TIT2 Title/songname/content description]
		'TIT3', // [#TIT3 Subtitle/Description refinement]
		'TKEY', // [#TKEY Initial key]
		'TLAN', // [#TLAN Language(s)]
		'TLEN', // [#TLEN Length]
		'TMED', // [#TMED Media type]
		'TOAL', // [#TOAL Original album/movie/show title]
		'TOFN', // [#TOFN Original filename]
		'TOLY', // [#TOLY Original lyricist(s)/text writer(s)]
		'TOPE', // [#TOPE Original artist(s)/performer(s)]
		'TORY', // [#TORY Original release year]
		'TOWN', // [#TOWN File owner/licensee]
		'TPE1', // [#TPE1 Lead performer(s)/Soloist(s)]
		'TPE2', // [#TPE2 Band/orchestra/accompaniment]
		'TPE3', // [#TPE3 Conductor/performer refinement]
		'TPE4', // [#TPE4 Interpreted, remixed, or otherwise modified by]
		'TPOS', // [#TPOS Part of a set]
		'TPUB', // [#TPUB Publisher]
		'TRCK', // [#TRCK Track number/Position in set]
		'TRDA', // [#TRDA Recording dates]
		'TRSN', // [#TRSN Internet radio station name]
		'TRSO', // [#TRSO Internet radio station owner]
		'TSIZ', // [#TSIZ Size]
		'TSRC', // [#TSRC ISRC (international standard recording code)]
		'TSSE', // [#TSEE Software/Hardware and settings used for encoding]
		'TYER', // [#TYER Year]
		'TXXX', // [#TXXX User defined text information frame]
		'UFID', // [#sec4.1 Unique file identifier]
		'USER', // [#sec4.23 Terms of use]
		'USLT', // [#sec4.9 Unsychronized lyric/text transcription]
		'WCOM', // [#WCOM Commercial information]
		'WCOP', // [#WCOP Copyright/Legal information]
		'WOAF', // [#WOAF Official audio file webpage]
		'WOAR', // [#WOAR Official artist/performer webpage]
		'WOAS', // [#WOAS Official audio source webpage]
		'WORS', // [#WORS Official internet radio station homepage]
		'WPAY', // [#WPAY Payment]
		'WPUB', // [#WPUB Publishers official webpage]
		'WXXX', // [#WXXX User defined URL link frame]
	);
	/**
	 * Number of tracks
	 * @var int
	 */
	private $tracks = 0;
	/**
	 * Storage for frames
	 * Asscociative. frameid => value
	 * @var array
	 */
	private $frames = array();
	/**
	 * Unparsed frames
	 * @var StringReader
	 */
	private $sframes = null;

	/**
	 * Constructor.
	 *
	 * @param File $file
	 * @param array $head
	 */
	public function __construct(File $file, array $head)
	{
		parent::__construct($file, $head);

		$flags = $head['flags'];
		$flags = (object)array(
			'unsync'       => ($flags >> 7) & 1,
			'extended'     => ($flags >> 6) & 1,
			'experimental' => ($flags >> 5) & 1
		);

		$tag = $this->stream->ReadBlock(10, $head['size']);
		$this->sframes = new StringReader($tag);
		$this->readFrames();

		$this->title  = $this->frameOrNull('TIT2');
		$this->artist = $this->frameOrNull('TPE1');
		$this->album  = $this->frameOrNull('TALB');

		if (array_key_exists('TRCK', $this->frames)) {
			sscanf($this->frames['TRCK'], '%d/%d', $t, $to);
			$this->track  = $t;
			$this->tracks = $to;
		}

		if (array_key_exists('TDRC', $this->frames))
			$this->year = $this->frames['TDRC'];

		if (array_key_exists('TCON', $this->frames))
			$this->genre  = self::GetIdOfGenre($this->frames['TCON']);

		//rprint($this->frames);
	}

	/**
	 * Returns the frame `$which` or `null` if the frame is not set
	 *
	 * @param string $which
	 * @return ID3v2Frame|ID3v2ImageFrame
	 */
	private function frameOrNull($which)
	{
		if (array_key_exists($which, $this->frames))
			return $this->frames[$which];

		return null;
	}

	/**
	 * Read the ID3 frames
	 */
	protected function readFrames()
	{
		if ($this->sframes->Position()-10 >= $this->head['size'])
			return;

		$h = $this->sframes->Read(10);
		if (!preg_match('/^[A-Z0-9]{4}/', $h))
			return;

		$h = unpack('a4id/Nsize/Cflags', $h);

		if (!strlen($h['id']))
			return;

		$d = $this->sframes->Read($h['size']);

		if ($h['id'] == 'APIC')
			$this->frames[$h['id']] = new ID3v2ImageFrame($h, $d, $this->version);
		else
			$this->frames[$h['id']] = new ID3v2Frame($h, $d, $this->version);

		$this->readFrames();
	}

	/**
	 * Returns the frames
	 *
	 * @return array
	 */
	public function Frames()
	{
		return $this->frames;
	}

	/**
	 * Returns true if at least one frame was found
	 *
	 * @return bool
	 */
	public function Valid()
	{
		return sizeof($this->frames) > 0;
	}

	/**
	 * Returns the number of tracks.
	 *
	 * @return int
	 */
	public function Tracks()
	{
		return $this->tracks;
	}

	/**
	 * Returns the image if existing
	 *
	 * @return string
	 */
	public function Image()
	{
		return $this->frameOrNull('APIC');
	}
}

/**
 * Representation of a ID3v2 frame
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Media
 */
class ID3v2Frame
{
	/**
	 * Frame header
	 * @var string
	 */
	protected $head  = null;
	/**
	 * Frame data
	 * @var string
	 */
	protected $data  = null;
	/**
	 * Normalized frame data
	 * @var string
	 */
	protected $cdata = null;
	/**
	 * Version of owning tag
	 * @var Version
	 */
	protected $version = null;

	/**
	 * Constructor
	 *
	 * @param array $head
	 * @param string $data
	 * @param Version $version
	 *  The version of the owning tag
	 */
	public function __construct(array $head, $data, Version $version=null)
	{
		$this->head    = $head;
		$this->data    = $data;
		$this->version = $version;
	}

	/**
	 * Returns the data normalized, i.e all binary data removed.
	 *
	 * @return string
	 */
	public function Data()
	{
		return $this->normalize($this->data);
	}

	/**
	 * Returns the raw data
	 *
	 * @return string
	 */
	public function RawData()
	{
		return $this->data;
	}

	/**
	 * Returns the frame ID
	 *
	 * @return string
	 */
	public function ID()
	{
		return $this->head['id'];
	}

	/**
	 * Is the frame a text frame or not
	 *
	 * @return bool
	 */
	public function IsTextual()
	{
		return $this->head['id'][0] == 'T';
	}

	/**
	 * Cast to string.
	 *
	 * @return string
	 *  The noramlized data
	 */
	public function __toString()
	{
		if (!$this->cdata)
		 	try { $this->cdata = $this->normalize($this->data); }
		 	catch (Exception $e) { $this->cdata = ''; }

		return $this->cdata;
	}

	/**
	 * Removes binary shit in the string
	 *
	 * @param string $str
	 * @return string
	 *  Converted to ISO-8859-1
	 */
	protected function normalize($str)
	{
		$d = null;

		if ($this->IsTextual()) {
			$sr  = new StringReader($this->data);
			// 00 = iso-8859-1
			// 01 = Unicode (ucs-2?)
			//
			// ID3v2.4.0
			// 00 = iso-8859-1
			// 01 = UTF-16
			// 02 = UTF-16BE
			// 03 = UTF-8
			$enc = ord($sr->Read());
			$d   = $sr->Read($this->head['size']-1);

			if ($enc > 0) {
				if ($this->version->Less(new Version(4,0)))
					$d = substr(mb_convert_encoding($d, 'ISO-8859-1', 'UCS-2'), 1);
				else {
					if ($enc == 1)
						$d = substr(mb_convert_encoding($d, 'ISO-8859-1', 'Unicode'), 1);
					elseif ($enc == 2)
						$d = substr(mb_convert_encoding($d, 'ISO-8859-1', 'UTF-16BE'), 1);
					elseif ($enc == 3)
						$d = utf8_decode($d);
				}
			}

			$sr->Dispose();
			unset($sr);
		}
		else
			$d = $this->data;

		return trim($d);
	}
}

/**
 * Representation of a ID3v2 frame containing an image
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Media
 */
class ID3v2ImageFrame extends ID3v2Frame
{
	/**
	 * Picture types
	 * @var array
	 */
	protected $pictypes = array(
		'00' => 'Other',
		'01' => '32x32 pixels \'file icon\' (PNG only)',
		'02' => 'Other file icon',
		'03' => 'Cover (front)',
		'04' => 'Cover (back)',
		'05' => 'Leaflet page',
		'06' => 'Media (e.g. lable side of CD)',
		'07' => 'Lead artist/lead performer/soloist',
		'08' => 'Artist/performer',
		'09' => 'Conductor',
		'0A' => 'Band/Orchestra',
		'0B' => 'Composer',
		'0C' => 'Lyricist/text writer',
		'0D' => 'Recording Location',
		'0E' => 'During recording',
		'0F' => 'During performance',
		'10' => 'Movie/video screen capture',
		'11' => 'A bright coloured fish',
		'12' => 'Illustration',
		'13' => 'Band/artist logotype',
		'14' => 'Publisher/Studio logotype'
	);

	/**
	 * Image's mime type
	 * @var string
	 */
	protected $mime = null;
	/**
	 * Type of image
	 * @see ID3v2ImageFrame::$pictype
	 * @var string
	 */
	protected $type = null;
	/**
	 * Image encoding
	 * @var string
	 */
	protected $enc  = null;

	/**
	 * Constructor
	 *
	 * @param array $head
	 * @param string $data
	 * @param Version $version
	 *  The version of the owning tag
	 */
	public function __construct(array $head, $data, Version $version=null)
	{
		parent::__construct($head, $data, $version);
	}

	/**
	 * Returns the encoding
	 *
	 * @return string
	 */
	public function Encoding()
	{
		if (!$this->cdata)
			$this->normalize($this->data);

		return $this->enc;
	}

	/**
	 * Returns the mime type
	 *
	 * @return string
	 */
	public function MimeType()
	{
		if (!$this->cdata)
			$this->normalize($this->data);

		return $this->mime;
	}

	/**
	 * Returns the type of image
	 *
	 * @return string
	 */
	public function Type()
	{
		if (!$this->cdata)
			$this->normalize($this->data);

		return $this->type;
	}

	/**
	 * Normalizes the data, i.e. parses the image frame and pulls out the
	 * encoding, mimetype etc.
	 *
	 * @param string $data
	 * @return string
	 *  Returns the image data
	 */
	protected function normalize($data)
	{
		if (!$this->cdata) {
			$sr         = new StringReader($this->data);
			$this->enc  = bin2hex($sr->Read());
			$this->mime = $sr->ReadToChar("\0");
			$this->type = bin2hex($sr->Read());

			while ($sr->Read() == "\0")
				;

			$this->cdata = $sr->ReadToEnd();

			$sr->Dispose();
			unset($sr);
		}

		return $this->cdata;
	}
}

/**
 * ID3v1 class.
 * Don't instantiate this class directly. Use {@see ID3::Create()} instead.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package IO
 */
class ID3v1 extends ID3
{
	public $type = self::TYPE_ID3V1;

	/**
	 * Tag size
	 * @var int
	 */
	public $size = 128;

	/**
	 * Creates an ID3v1 object
	 *
	 * @param File $file
	 * @param string $head
	 *  The first 10 bytes of the file
	 */
	public function __construct(File $file, array $head)
	{
		parent::__construct($file, $head);
		$this->stream->Seek($file->size - $this->size);
		$this->tag = $this->stream->Read($this->size);

		$this->ParseTag($this->tag);
	}

	/**
	 * Parse the ID3v1 tag - the last `128` bytes of the file.
	 *
	 * @throws ID3Exception
	 *  If the ID3 tag is malformed
	 * @throws ID3v2AsID3v1
	 *  If the tag isn't a version 1 tag but rather a misversioned version 2 tag.
	 * @param string $tag
	 */
	public function ParseTag($tag)
	{
		if (substr($tag,0,strlen($this->fileIdentifier)) != $this->fileIdentifier) {
			$file = new ID3v2($this->file, $this->head);
			if ($file->Valid())
				throw new ID3v2AsID3v1("ID3v2 file encoded as ID3v1");
			else
				throw new ID3Exception("Malformed ID3v1 tag");
		}

		$tag = substr($tag, 3);
		$sr = new StringReader($tag);

		// If track info is embedded the 29th byte of the comment is a binary 0
		// and the 30th byte is the track info it self
		$trackbyte = ord(substr($tag, (30*3)+4+29, 1)) == 0;

		$this->title   = trim($sr->Read(30));
		$this->artist  = trim($sr->Read(30));
		$this->album   = trim($sr->Read(30));
		$this->year    = (int)trim($sr->Read(4));
		$this->comment = trim($sr->Read($trackbyte ? 28 : 30));

		if ($trackbyte) {
			$sr->Read(); // Skip the 29th byte of the comment
			$track = $sr->Read();
			if (ord($track) != 0)
				$this->track = ord($track);
		}

		$this->genre = ord($sr->Read());
	}
}

/**
 * Exception for when a tag is misversioned as version 1 but really is
 * a version 2 tag.
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Media
 */
class ID3v2AsID3v1 extends Exception {}

/**
 * General ID3 exception class
 *
 * @author Pontus Östlund <pontus@poppa.se>
 * @package Media
 */
class ID3Exception extends Exception {}
?>