<?php
/**
 * AI Knowledge Base - lets admins feed extra context (documents, web pages)
 * to the AI auto-reply so it can answer questions specific to the site.
 *
 * @since      0.0.1
 *
 * @package    captain-live-chat-pro
 * @subpackage captain-live-chat/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CAPTLC_Knowledge' ) ) {

	/**
	 * Class CAPTLC_Knowledge
	 */
	class CAPTLC_Knowledge {

		/**
		 * Option key storing all knowledge base entries. Deliberately not
		 * autoloaded (see class-captlc-activator.php upgrade routine) since
		 * extracted document text can be sizeable and this isn't needed on
		 * every page load - only when building an AI reply.
		 */
		const OPTION_KEY = 'captlc_ai_knowledge';

		/**
		 * Max number of knowledge entries a site can store at once.
		 */
		const MAX_ENTRIES = 15;

		/**
		 * Safety ceiling on raw extracted characters kept per source. This
		 * is NOT the real scaling mechanism anymore - relevance-scored
		 * chunking (see chunk_text()/get_context_text()) is - it just stops
		 * one runaway page from bloating the wp_options row indefinitely.
		 * ~200,000 chars is roughly a 40-page document.
		 */
		const MAX_CHARS_PER_ENTRY = 200000;

		/**
		 * Ceiling on the combined characters stored across ALL entries, so
		 * the single wp_options row that holds the knowledge base stays
		 * well below typical MySQL max_allowed_packet limits.
		 */
		const MAX_TOTAL_CHARS = 1000000;

		/**
		 * Biggest uploaded file accepted (bytes) and biggest decompressed
		 * PDF stream accepted (bytes), plus a cap on streams scanned.
		 */
		const MAX_UPLOAD_BYTES     = 8388608;
		const MAX_PDF_STREAM_BYTES = 5242880;
		const MAX_PDF_STREAMS      = 5000;

		/**
		 * Max bytes read from a remote page.
		 */
		const MAX_REMOTE_BYTES = 2097152;

		/**
		 * Max characters of an entry returned in the admin list response.
		 */
		const LIST_PREVIEW_CHARS = 20000;

		/**
		 * Max combined characters across the chunks actually injected into
		 * a single AI request. Kept modest since this is a prompt sent to
		 * (often free-tier) models on every visitor message - but because
		 * get_context_text() now picks the most RELEVANT chunks for the
		 * visitor's actual question rather than "whatever comes first",
		 * this budget goes much further than it used to.
		 */
		const MAX_CONTEXT_CHARS = 8000;

		/**
		 * Target size (characters) of one retrievable chunk. Sources are
		 * split into pieces around this size, on sentence boundaries, so
		 * a source of any length can be searched and only its relevant
		 * pieces sent to the AI - instead of truncating the whole source
		 * at a fixed length and hoping the important part came first.
		 */
		const CHUNK_SIZE = 700;

		/**
		 * Max number of best-matching chunks sent per request when the
		 * visitor's message actually matched something. Fewer, relevant
		 * chunks beat padding the prompt with unrelated text - it keeps
		 * every request small (cheaper, faster, friendlier to free-tier
		 * token limits) and stops unrelated material distracting the model.
		 */
		const MAX_MATCHED_CHUNKS = 6;

		/**
		 * Smaller budget for the broad fallback sample used when the
		 * visitor's message has no keywords to match (e.g. "hi") - there's
		 * no specific question to answer, so there's no need to spend the
		 * full context budget on it.
		 */
		const FALLBACK_CONTEXT_CHARS = 3000;

		/**
		 * Very common English words excluded when scoring a chunk's
		 * relevance to the visitor's question, so e.g. "the"/"is" matching
		 * everywhere doesn't drown out the words that actually carry the
		 * question's meaning.
		 */
		const BASE_STOPWORDS = array(
			'a',
			'an',
			'the',
			'is',
			'are',
			'was',
			'were',
			'be',
			'been',
			'being',
			'of',
			'to',
			'in',
			'on',
			'for',
			'and',
			'or',
			'with',
			'at',
			'by',
			'from',
			'this',
			'that',
			'it',
			'as',
			'do',
			'does',
			'did',
			'you',
			'your',
			'i',
			'we',
			'can',
			'so',
			'if',
			'my',
			'me',
			'us',
			'our',
			'about',
			'have',
			'has',
			'had',
			'not',
			'but',
			'will',
			'would',
		);

		/**
		 * Stop words, filterable so sites with non-English content can add
		 * their own language's very common words.
		 *
		 * @return string[]
		 */
		private static function get_stopwords() {
			static $cache = null;

			if ( null === $cache ) {
				$list  = apply_filters( 'captlc_ai_knowledge_stopwords', self::BASE_STOPWORDS );
				$cache = array_flip( array_map( 'mb_strtolower', (array) $list ) );
			}

			return $cache;
		}

		/**
		 * Constructor - registers AJAX hooks.
		 */
		public function __construct() {
			add_action( 'wp_ajax_captlc_get_knowledge', array( $this, 'get_knowledge' ) );
			add_action( 'wp_ajax_captlc_add_knowledge_url', array( $this, 'add_knowledge_url' ) );
			add_action( 'wp_ajax_captlc_refresh_knowledge_url', array( $this, 'refresh_knowledge_url' ) );
			add_action( 'wp_ajax_captlc_upload_knowledge_file', array( $this, 'upload_knowledge_file' ) );
			add_action( 'wp_ajax_captlc_delete_knowledge', array( $this, 'delete_knowledge' ) );
		}

		/**
		 * Returns all saved entries (without the full extracted text, to
		 * keep the list response light - the AI prompt builder reads the
		 * option directly for full content).
		 *
		 * @return array
		 */
		private static function get_entries() {
			$raw     = (array) get_option( self::OPTION_KEY, array() );
			$entries = array();

			foreach ( $raw as $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
					continue;
				}

				$entries[] = array_merge(
					array(
						'type'       => 'file',
						'title'      => '',
						'source'     => '',
						'created_at' => '',
					),
					$entry
				);
			}

			return $entries;
		}

		/**
		 * Returns an entry's chunks. New entries store chunks only; entries
		 * saved by earlier builds also carry 'content', which is chunked on
		 * the fly when 'chunks' is missing.
		 *
		 * @param array $entry Stored entry.
		 * @return string[]
		 */
		private static function entry_chunks( array $entry ) {
			if ( isset( $entry['chunks'] ) && is_array( $entry['chunks'] ) && ! empty( $entry['chunks'] ) ) {
				return $entry['chunks'];
			}

			return self::chunk_text( isset( $entry['content'] ) ? $entry['content'] : '' );
		}

		/**
		 * Total characters currently stored across all entries.
		 *
		 * @param array $entries Entries.
		 * @return int
		 */
		private static function total_chars( array $entries ) {
			$total = 0;

			foreach ( $entries as $entry ) {
				if ( isset( $entry['char_count'] ) ) {
					$total += (int) $entry['char_count'];
				} elseif ( isset( $entry['content'] ) ) {
					$total += mb_strlen( $entry['content'] );
				} else {
					foreach ( self::entry_chunks( $entry ) as $chunk ) {
						$total += mb_strlen( $chunk );
					}
				}
			}

			return $total;
		}

		/**
		 * Takes a short-lived lock so two admins saving at the same moment
		 * cannot overwrite each other's change (the whole knowledge base is
		 * one option, so every change is read-modify-write).
		 *
		 * @return bool True when the lock was taken.
		 */
		private static function acquire_lock() {
			$lock_key = 'captlc_ai_kb_lock';

			for ( $i = 0; $i < 20; $i++ ) {
				// add_option() is atomic on the unique option_name.
				if ( add_option( $lock_key, time(), '', false ) ) {
					return true;
				}

				$taken = (int) get_option( $lock_key, 0 );

				if ( $taken && ( time() - $taken ) > 30 ) {
					delete_option( $lock_key ); // Stale lock from a crashed request.
				}

				usleep( 150000 );
			}

			return false;
		}

		/**
		 * Releases the lock taken by acquire_lock().
		 *
		 * @return void
		 */
		private static function release_lock() {
			delete_option( 'captlc_ai_kb_lock' );
		}

		/**
		 * Persists entries, explicitly keeping the option un-autoloaded.
		 *
		 * @param array $entries Entries to save.
		 * @return void
		 */
		private static function save_entries( $entries ) {
			update_option( self::OPTION_KEY, array_values( $entries ), false );
		}

		/**
		 * Returns the combined, relevance-ranked knowledge context text
		 * ready to be appended to the AI system prompt. Empty string if
		 * nothing saved.
		 *
		 * Rather than truncating each source at a fixed length and
		 * injecting sources in whatever order they were added (the old
		 * behavior - which meant a large source's important content could
		 * simply never be reached, and a source added later could get
		 * silently dropped once the combined budget filled up), every
		 * source is split into small chunks and every chunk is scored
		 * against the visitor's own message. Only the best-matching chunks
		 * - up to MAX_CONTEXT_CHARS combined - are sent. This is what lets
		 * a source of any size (a whole documentation site, not just one
		 * FAQ page) be usable: the AI only ever needs the few chunks
		 * relevant to THIS question, not the entire knowledge base at once.
		 *
		 * @param string $query The visitor's message, used to rank chunks by
		 *                      relevance. Empty string falls back to a broad
		 *                      sample across sources (e.g. for a generic
		 *                      "hi" with no real keywords to match against).
		 * @return string
		 */
		public static function get_context_text( $query = '' ) {
			$entries = self::get_entries();

			if ( empty( $entries ) ) {
				return '';
			}

			$pool = self::build_chunk_pool( $entries );

			if ( empty( $pool ) ) {
				return '';
			}

			$query_terms = self::query_terms( $query );

			if ( ! empty( $query_terms ) ) {
				foreach ( $pool as &$item ) {
					$item['score'] = self::score_chunk( $query_terms, $item['text'] );
				}
				unset( $item );

				usort(
					$pool,
					function ( $a, $b ) {
						return $b['score'] <=> $a['score'];
					}
				);
			}

			$budget = self::MAX_CONTEXT_CHARS;

			if ( empty( $query_terms ) || 0 === $pool[0]['score'] ) {
				$budget = self::FALLBACK_CONTEXT_CHARS;
				// Nothing scored (empty query, or a message that shares no
				// real keywords with anything stored - e.g. "hi") - fall
				// back to a broad sample so the AI still has *some*
				// grounding, rather than sending no reference material.
				$pool = self::round_robin_sample( $entries );
			} else {
				// Something matched: send ONLY the matching chunks (best
				// first), not zero-score filler up to the size budget.
				$pool = array_slice(
					array_filter(
						$pool,
						function ( $item ) {
							return $item['score'] > 0;
						}
					),
					0,
					self::MAX_MATCHED_CHUNKS
				);
			}

			$chunks_out = array();
			$total      = 0;
			$last_title = null;

			foreach ( $pool as $item ) {
				$remaining = $budget - $total;
				if ( $remaining <= 0 ) {
					break;
				}

				$piece = mb_substr( $item['text'], 0, $remaining );

				// Group consecutive chunks from the same source under one
				// heading instead of repeating it per chunk.
				if ( $item['title'] !== $last_title ) {
					$chunks_out[] = '### ' . $item['title'];
					$last_title   = $item['title'];
				}

				$chunks_out[] = $piece;
				$total       += mb_strlen( $piece );
			}

			if ( empty( $chunks_out ) ) {
				return '';
			}

			return "\n\n" . __( 'Reference material - use this to answer questions when relevant:', 'captain-live-chat-pro' ) . "\n\n<reference_material>\n" . implode( "\n\n", $chunks_out ) . "\n</reference_material>";
		}

		/**
		 * Flattens every saved entry into a flat list of { title, text }
		 * chunks ready to be scored and ranked. Entries saved before
		 * chunking existed have no 'chunks' key - they're chunked here on
		 * the fly from their stored 'content' so nothing needs a manual
		 * re-save to benefit from relevance ranking.
		 *
		 * @param array $entries Stored entries.
		 * @return array List of array{title:string,text:string}.
		 */
		private static function build_chunk_pool( array $entries ) {
			$pool = array();

			foreach ( $entries as $entry ) {
				$title  = isset( $entry['title'] ) && $entry['title'] ? $entry['title'] : __( 'Untitled', 'captain-live-chat-pro' );
				$chunks = self::entry_chunks( $entry );

				foreach ( $chunks as $chunk ) {
					if ( '' !== trim( (string) $chunk ) ) {
						$pool[] = array(
							'title' => $title,
							'text'  => $chunk,
							'score' => 0,
						);
					}
				}
			}

			return $pool;
		}

		/**
		 * Splits text into ~CHUNK_SIZE-character pieces on sentence
		 * boundaries (never mid-sentence, except for a single sentence
		 * that alone exceeds the chunk size). Each chunk becomes an
		 * independently retrievable, independently scorable unit - this is
		 * what lets a source of any length be searched instead of just
		 * truncated.
		 *
		 * @param string $text Plain text to chunk.
		 * @return string[]
		 */
		private static function chunk_text( $text ) {
			$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );

			if ( '' === $text ) {
				return array();
			}

			$sentences = preg_split( '/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
			if ( empty( $sentences ) ) {
				$sentences = array( $text );
			}

			$chunks  = array();
			$current = '';

			foreach ( $sentences as $sentence ) {
				// A single sentence longer than the target chunk size on its
				// own (e.g. an un-punctuated wall of text some pages have) -
				// flush what's pending, then hard-split the sentence itself
				// so it's still searchable instead of forming one giant chunk.
				if ( mb_strlen( $sentence ) > self::CHUNK_SIZE ) {
					if ( '' !== $current ) {
						$chunks[] = trim( $current );
						$current  = '';
					}
					foreach ( mb_str_split( $sentence, self::CHUNK_SIZE ) as $piece ) {
						$chunks[] = trim( $piece );
					}
					continue;
				}

				if ( '' !== $current && ( mb_strlen( $current ) + mb_strlen( $sentence ) + 1 ) > self::CHUNK_SIZE ) {
					$chunks[] = trim( $current );
					$current  = '';
				}

				$current .= ( '' === $current ? '' : ' ' ) . $sentence;
			}

			if ( '' !== trim( $current ) ) {
				$chunks[] = trim( $current );
			}

			return $chunks;
		}

		/**
		 * Lowercases and splits text into word tokens for scoring.
		 *
		 * @param string $text Text to tokenize.
		 * @return string[]
		 */
		private static function tokenize( $text ) {
			$text = mb_strtolower( (string) $text );

			// Letters, combining marks (Devanagari / Gujarati vowel signs are
			// marks, not letters) and digits, in any script.
			if ( ! preg_match_all( '/[\p{L}\p{M}\p{N}]+/u', $text, $matches ) ) {
				return array();
			}

			$tokens = array();

			foreach ( $matches[0] as $token ) {
				// Chinese / Japanese / Thai have no spaces between words, so a
				// whole sentence is one token. Index those scripts as
				// overlapping two-character pieces instead.
				if ( mb_strlen( $token ) > 2 && preg_match( '/^[\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}]+$/u', $token ) ) {
					$len = mb_strlen( $token );

					for ( $i = 0; $i < $len - 1; $i++ ) {
						$tokens[] = mb_substr( $token, $i, 2 );
					}

					continue;
				}

				$tokens[] = $token;
			}

			return $tokens;
		}

		/**
		 * Tokenizes the visitor's message into the meaningful terms used to
		 * score chunks - stopwords and 2-letter noise removed since they'd
		 * match almost every chunk and dilute the ranking.
		 *
		 * @param string $query Visitor's message.
		 * @return string[]
		 */
		private static function query_terms( $query ) {
			$terms = self::tokenize( $query );
			$terms = array_filter(
				$terms,
				function ( $term ) {
					$stop = self::get_stopwords();

					if ( isset( $stop[ $term ] ) ) {
						return false;
					}

					// Latin words need 3+ characters; words in other scripts
					// (and CJK pairs) are meaningful from 2.
					return mb_strlen( $term ) > 2 || ( mb_strlen( $term ) > 1 && preg_match( '/[^\x00-\x7F]/', $term ) );
				}
			);
			return array_values( array_unique( $terms ) );
		}

		/**
		 * Scores one chunk's relevance to the given query terms: how many
		 * of the query's words it contains, weighted a little toward
		 * longer/rarer words, and normalized so a chunk doesn't win purely
		 * for being long. This is a lightweight lexical (keyword-overlap)
		 * scorer, not semantic search - no embeddings/API calls involved,
		 * so retrieval works instantly and for free with any AI provider.
		 *
		 * @param string[] $query_terms Tokenized, stopword-filtered query terms.
		 * @param string   $chunk_text  The chunk's text.
		 * @return float
		 */
		private static function score_chunk( array $query_terms, $chunk_text ) {
			// Cheap pre-check: a term can only match as a token if it appears
			// as a substring, so skip the costly tokenizing for the (many)
			// chunks that contain none of the query terms.
			$lower = mb_strtolower( (string) $chunk_text );
			$hit   = false;

			foreach ( $query_terms as $term ) {
				if ( false !== strpos( $lower, $term ) ) {
					$hit = true;
					break;
				}
			}

			if ( ! $hit ) {
				return 0.0;
			}

			$chunk_terms = self::tokenize( $chunk_text );
			if ( empty( $chunk_terms ) ) {
				return 0.0;
			}

			$counts = array_count_values( $chunk_terms );
			$score  = 0.0;

			foreach ( $query_terms as $term ) {
				if ( isset( $counts[ $term ] ) ) {
					$weight = mb_strlen( $term ) >= 5 ? 2 : 1;
					// Cap how much repeating the same word inside one chunk
					// can inflate its score.
					$score += $weight * min( $counts[ $term ], 3 );
				}
			}

			if ( 0.0 === $score ) {
				return 0.0;
			}

			// Mild length normalization so a very long chunk doesn't
			// automatically outrank a short, tightly-relevant one.
			return $score / sqrt( max( count( $chunk_terms ), 20 ) );
		}

		/**
		 * Picks a broad, evenly-spread sample of chunks across every
		 * source - one chunk per source per round - for messages with no
		 * usable keywords to score against (a generic greeting, or an empty
		 * query). Keeps every source at least somewhat represented instead
		 * of e.g. always favoring whichever source happens to be first.
		 *
		 * @param array $entries Stored entries.
		 * @return array List of array{title:string,text:string,score:int}.
		 */
		private static function round_robin_sample( array $entries ) {
			$per_source = array();

			foreach ( $entries as $entry ) {
				$title  = isset( $entry['title'] ) && $entry['title'] ? $entry['title'] : __( 'Untitled', 'captain-live-chat-pro' );
				$chunks = self::entry_chunks( $entry );

				$chunks = array_values(
					array_filter(
						$chunks,
						function ( $c ) {
							return '' !== trim( (string) $c );
						}
					)
				);

				if ( ! empty( $chunks ) ) {
					$per_source[] = array(
						'title'  => $title,
						'chunks' => $chunks,
					);
				}
			}

			$pool  = array();
			$round = 0;
			$added = true;

			while ( $added ) {
				$added = false;
				foreach ( $per_source as $source ) {
					if ( isset( $source['chunks'][ $round ] ) ) {
						$pool[] = array(
							'title' => $source['title'],
							'text'  => $source['chunks'][ $round ],
							'score' => 0,
						);
						$added  = true;
					}
				}
				++$round;
			}

			return $pool;
		}

		/**
		 * Lists saved knowledge entries: metadata plus the full extracted
		 * text, so the admin can inspect exactly what the AI has -
		 * e.g. spot a URL that yielded almost no text because the page
		 * needs JavaScript to render. Already bounded (MAX_CHARS_PER_ENTRY
		 * per entry, MAX_ENTRIES entries total), so sending it in full on
		 * this admin-only settings screen is fine.
		 *
		 * @return void
		 */
		public function get_knowledge() {
			check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'captain-live-chat-pro' ) ), 403 );
			}

			$entries = array_map( array( __CLASS__, 'to_list_item' ), self::get_entries() );

			wp_send_json_success( array( 'entries' => $entries ) );
		}

		/**
		 * Shapes one stored entry into the list-view shape shared by
		 * get_knowledge() and the "just added"/"just refreshed" responses
		 * - metadata plus the full extracted text.
		 *
		 * @param array $entry Full stored entry (including 'content').
		 * @return array
		 */
		private static function to_list_item( $entry ) {
			$chunks  = self::entry_chunks( $entry );
			$content = isset( $entry['content'] ) ? $entry['content'] : implode( ' ', $chunks );
			$total   = isset( $entry['char_count'] ) ? (int) $entry['char_count'] : mb_strlen( $content );

			return array(
				'id'                => $entry['id'],
				'type'              => $entry['type'],
				'title'             => $entry['title'],
				'source'            => $entry['source'],
				'char_count'        => $total,
				'chunk_count'       => count( $chunks ),
				'content'           => mb_substr( $content, 0, self::LIST_PREVIEW_CHARS ),
				'content_truncated' => mb_strlen( $content ) > self::LIST_PREVIEW_CHARS,
				'created_at'        => $entry['created_at'],
				'updated_at'        => isset( $entry['updated_at'] ) ? $entry['updated_at'] : $entry['created_at'],
			);
		}

		/**
		 * Fetches a URL and extracts its readable title + plain text.
		 * Shared by add_knowledge_url() (new entry) and
		 * refresh_knowledge_url() (re-fetch an existing entry) so both
		 * follow the exact same rules for what gets extracted and how
		 * much of it is kept.
		 *
		 * @param string $url URL to fetch.
		 * @return array{title:string,text:string}|WP_Error
		 */
		private static function fetch_and_extract_url( $url ) {
			if ( empty( $url ) || ! wp_http_validate_url( $url ) ) {
				return new WP_Error( 'invalid_url', __( 'Please enter a valid, publicly reachable URL.', 'captain-live-chat-pro' ) );
			}

			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 15,
					'redirection'         => 3,
					'limit_response_size' => self::MAX_REMOTE_BYTES,
					'user-agent'          => 'Captain Live Chat/' . CAPTLC_PRO_VERSION,
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'unreachable', __( 'Could not reach that URL.', 'captain-live-chat-pro' ) );
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				return new WP_Error(
					'http_error',
					sprintf(
						/* translators: %d: HTTP status code returned by the remote server. */
						__( 'The page returned an error (HTTP %d).', 'captain-live-chat-pro' ),
						$code
					)
				);
			}

			$content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );

			if ( '' !== $content_type && false === strpos( $content_type, 'text/' ) && false === strpos( $content_type, 'xml' ) ) {
				return new WP_Error( 'bad_type', __( 'That link is not a web page. Upload PDF and text files with the file option instead.', 'captain-live-chat-pro' ) );
			}

			$html = wp_remote_retrieve_body( $response );
			$text = self::html_to_text( $html );

			if ( '' === trim( $text ) ) {
				return new WP_Error( 'no_text', __( 'Could not extract any readable text from that page.', 'captain-live-chat-pro' ) );
			}

			$title = self::extract_html_title( $html );
			$title = $title ? $title : wp_parse_url( $url, PHP_URL_HOST );

			return array(
				'title' => sanitize_text_field( $title ),
				'text'  => mb_substr( $text, 0, self::MAX_CHARS_PER_ENTRY ),
			);
		}

		/**
		 * Fetches a URL, strips it down to plain text, and saves it as a
		 * new knowledge entry.
		 *
		 * @return void
		 */
		public function add_knowledge_url() {
			check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'captain-live-chat-pro' ) ), 403 );
			}

			if ( count( self::get_entries() ) >= self::MAX_ENTRIES ) {
				wp_send_json_error( array( 'message' => __( 'Knowledge base is full. Delete a source before adding another.', 'captain-live-chat-pro' ) ) );
			}

			$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

			// Fetch BEFORE taking the lock - the network call is slow.
			$fetched = self::fetch_and_extract_url( $url );
			if ( is_wp_error( $fetched ) ) {
				wp_send_json_error( array( 'message' => $fetched->get_error_message() ) );
			}

			$entry = self::build_entry( 'url', $fetched['title'], $url, $fetched['text'] );

			self::append_entry_or_fail( $entry );
		}

		/**
		 * Builds a storable entry. Only chunks are stored (not the same
		 * text a second time) to keep the option small.
		 *
		 * @param string $type   'url' or 'file'.
		 * @param string $title  Display title.
		 * @param string $source URL or file name.
		 * @param string $text   Extracted plain text.
		 * @return array
		 */
		private static function build_entry( $type, $title, $source, $text ) {
			$text   = mb_substr( $text, 0, self::MAX_CHARS_PER_ENTRY );
			$chunks = self::chunk_text( $text );

			return array(
				'id'         => wp_generate_uuid4(),
				'type'       => $type,
				'title'      => $title,
				'source'     => $source,
				'chunks'     => $chunks,
				'char_count' => mb_strlen( implode( ' ', $chunks ) ),
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			);
		}

		/**
		 * Adds an entry under the lock, re-checking the limits against the
		 * freshest data, then answers the AJAX request.
		 *
		 * @param array $entry Entry from build_entry().
		 * @return void
		 */
		private static function append_entry_or_fail( array $entry ) {
			if ( ! self::acquire_lock() ) {
				wp_send_json_error( array( 'message' => __( 'The knowledge base is busy. Please try again in a moment.', 'captain-live-chat-pro' ) ) );
			}

			$entries = self::get_entries();

			if ( count( $entries ) >= self::MAX_ENTRIES ) {
				self::release_lock();
				wp_send_json_error( array( 'message' => __( 'Knowledge base is full. Delete a source before adding another.', 'captain-live-chat-pro' ) ) );
			}

			if ( self::total_chars( $entries ) + $entry['char_count'] > self::MAX_TOTAL_CHARS ) {
				self::release_lock();
				wp_send_json_error( array( 'message' => __( 'The knowledge base has reached its total size limit. Delete a source before adding another.', 'captain-live-chat-pro' ) ) );
			}

			$entries[] = $entry;
			self::save_entries( $entries );
			self::release_lock();

			wp_send_json_success( array( 'entry' => self::to_list_item( $entry ) ) );
		}

		/**
		 * Re-fetches an existing URL entry's source and overwrites its
		 * stored content/title in place - same id, same position in the
		 * list. Lets an admin manually pull in a site's latest content
		 * without deleting and re-adding the entry (which would also lose
		 * its position and generate a new id).
		 *
		 * @return void
		 */
		public function refresh_knowledge_url() {
			check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'captain-live-chat-pro' ) ), 403 );
			}

			$id      = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
			$entries = self::get_entries();
			$source  = '';
			$found   = false;

			foreach ( $entries as $entry ) {
				if ( $entry['id'] === $id ) {
					$found  = true;
					$source = $entry['source'];

					if ( 'url' !== $entry['type'] ) {
						wp_send_json_error( array( 'message' => __( 'Only link sources can be refreshed - uploaded files have no live source to re-fetch.', 'captain-live-chat-pro' ) ) );
					}
					break;
				}
			}

			if ( ! $found ) {
				wp_send_json_error( array( 'message' => __( 'Entry not found.', 'captain-live-chat-pro' ) ) );
			}

			// Fetch BEFORE taking the lock - the network call is slow.
			$fetched = self::fetch_and_extract_url( $source );
			if ( is_wp_error( $fetched ) ) {
				wp_send_json_error( array( 'message' => $fetched->get_error_message() ) );
			}

			$fresh = self::build_entry( 'url', $fetched['title'], $source, $fetched['text'] );

			if ( ! self::acquire_lock() ) {
				wp_send_json_error( array( 'message' => __( 'The knowledge base is busy. Please try again in a moment.', 'captain-live-chat-pro' ) ) );
			}

			// Re-read under the lock: another admin may have changed the list.
			$entries = self::get_entries();
			$index   = null;

			foreach ( $entries as $i => $entry ) {
				if ( $entry['id'] === $id ) {
					$index = $i;
					break;
				}
			}

			if ( null === $index ) {
				self::release_lock();
				wp_send_json_error( array( 'message' => __( 'Entry not found.', 'captain-live-chat-pro' ) ) );
			}

			$others = $entries;
			unset( $others[ $index ] );

			if ( self::total_chars( $others ) + $fresh['char_count'] > self::MAX_TOTAL_CHARS ) {
				self::release_lock();
				wp_send_json_error( array( 'message' => __( 'The knowledge base has reached its total size limit. Delete a source before refreshing this one.', 'captain-live-chat-pro' ) ) );
			}

			$entries[ $index ]['title']      = $fresh['title'];
			$entries[ $index ]['chunks']     = $fresh['chunks'];
			$entries[ $index ]['char_count'] = $fresh['char_count'];
			$entries[ $index ]['updated_at'] = $fresh['updated_at'];
			unset( $entries[ $index ]['content'] );

			self::save_entries( $entries );
			self::release_lock();

			wp_send_json_success( array( 'entry' => self::to_list_item( $entries[ $index ] ) ) );
		}

		/**
		 * Accepts an uploaded .txt or .pdf file, extracts its text, and
		 * saves it as a knowledge entry.
		 *
		 * @return void
		 */
		public function upload_knowledge_file() {
			check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'captain-live-chat-pro' ) ), 403 );
			}

			if ( count( self::get_entries() ) >= self::MAX_ENTRIES ) {
				wp_send_json_error( array( 'message' => __( 'Knowledge base is full. Delete a source before adding another.', 'captain-live-chat-pro' ) ) );
			}

			if ( empty( $_FILES['captlc_knowledge_file'] ) || ! isset( $_FILES['captlc_knowledge_file']['tmp_name'], $_FILES['captlc_knowledge_file']['name'] ) ) {
				wp_send_json_error( array( 'message' => __( 'No file received.', 'captain-live-chat-pro' ) ) );
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the temporary path is only passed to is_uploaded_file() / filesize() / wp_check_filetype_and_ext() below.
			$tmp_name = (string) $_FILES['captlc_knowledge_file']['tmp_name'];
			$error    = isset( $_FILES['captlc_knowledge_file']['error'] ) ? (int) $_FILES['captlc_knowledge_file']['error'] : UPLOAD_ERR_OK;

			if ( UPLOAD_ERR_OK !== $error || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
				wp_send_json_error( array( 'message' => __( 'The file could not be uploaded. Please try again.', 'captain-live-chat-pro' ) ) );
			}

			if ( (int) filesize( $tmp_name ) > self::MAX_UPLOAD_BYTES ) {
				wp_send_json_error( array( 'message' => __( 'File too large. Maximum size is 8 MB.', 'captain-live-chat-pro' ) ) );
			}

			$allowed_types = array(
				'text/plain'      => 'txt',
				'application/pdf' => 'pdf',
			);

			if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			// Determine the file's REAL type from its bytes + extension
			// rather than trusting the browser-supplied `type` field, which
			// is client input and easily spoofed.
			$original_name = sanitize_file_name( wp_unslash( $_FILES['captlc_knowledge_file']['name'] ) );
			$checked       = wp_check_filetype_and_ext(
				$tmp_name,
				$original_name,
				array(
					'txt' => 'text/plain',
					'pdf' => 'application/pdf',
				)
			);
			$file_type     = isset( $checked['type'] ) ? $checked['type'] : false;

			if ( ! $file_type || ! isset( $allowed_types[ $file_type ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Only .txt and .pdf files are supported right now.', 'captain-live-chat-pro' ) ) );
			}

			$extension = $allowed_types[ $file_type ];

			// Read the text straight from PHP's temporary upload file. It is
			// never moved into the public uploads folder, so nothing can be
			// left behind there if extraction fails.
			$raw_bytes = file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( false === $raw_bytes ) {
				$raw_bytes = '';
			}

			$text = 'pdf' === $extension
				? self::extract_pdf_text( $raw_bytes )
				: self::clean_whitespace( self::to_utf8( $raw_bytes ) );

			unset( $raw_bytes );

			if ( '' === trim( (string) $text ) ) {
				wp_send_json_error(
					array(
						'message' => 'pdf' === $extension
							? __( 'Could not extract text from this PDF. Scanned/image-only PDFs and PDFs with custom fonts (common for Hindi, Gujarati and other non-Latin text) are not supported - paste the text into a .txt file instead.', 'captain-live-chat-pro' )
							: __( 'This file appears to be empty.', 'captain-live-chat-pro' ),
					)
				);
			}

			if ( '' === $original_name ) {
				$original_name = __( 'Untitled document', 'captain-live-chat-pro' );
			}

			$entry = self::build_entry( 'file', $original_name, $original_name, $text );

			self::append_entry_or_fail( $entry );
		}

		/**
		 * Deletes a single knowledge entry by ID.
		 *
		 * @return void
		 */
		public function delete_knowledge() {
			check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'captain-live-chat-pro' ) ), 403 );
			}

			$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
			if ( ! $id ) {
				wp_send_json_error( array( 'message' => __( 'Missing entry.', 'captain-live-chat-pro' ) ) );
			}

			if ( ! self::acquire_lock() ) {
				wp_send_json_error( array( 'message' => __( 'The knowledge base is busy. Please try again in a moment.', 'captain-live-chat-pro' ) ) );
			}

			$entries = array_filter(
				self::get_entries(),
				function ( $entry ) use ( $id ) {
					return $entry['id'] !== $id;
				}
			);

			self::save_entries( $entries );
			self::release_lock();

			wp_send_json_success();
		}

		/**
		 * Collapses an HTML document down to readable body text: strips
		 * script/style blocks, tags, then normalises whitespace.
		 *
		 * @param string $html Raw HTML.
		 * @return string
		 */
		private static function html_to_text( $html ) {
			$stripped = preg_replace( '#<(script|style|noscript|svg|nav|footer)\b[^>]*>.*?</\1>#is', ' ', $html );
			// On a PCRE failure (null) keep the original markup; tags are removed below anyway.
			$html   = null !== $stripped ? $stripped : $html;
			$breaks = preg_replace( '#<(br|/p|/div|/li|/h[1-6])\b[^>]*>#i', "\n", $html );
			$html   = null !== $breaks ? $breaks : $html;
			$text   = wp_strip_all_tags( $html, true );
			$text   = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

			return self::clean_whitespace( $text );
		}

		/**
		 * Converts raw file bytes to valid UTF-8. Handles UTF-8 (with or
		 * without BOM), UTF-16 with BOM, and falls back to Windows-1252.
		 *
		 * @param string $bytes Raw file contents.
		 * @return string
		 */
		private static function to_utf8( $bytes ) {
			$bytes = (string) $bytes;

			if ( 0 === strpos( $bytes, "\xEF\xBB\xBF" ) ) {
				$bytes = substr( $bytes, 3 );
			} elseif ( 0 === strpos( $bytes, "\xFF\xFE" ) ) {
				return (string) mb_convert_encoding( substr( $bytes, 2 ), 'UTF-8', 'UTF-16LE' );
			} elseif ( 0 === strpos( $bytes, "\xFE\xFF" ) ) {
				return (string) mb_convert_encoding( substr( $bytes, 2 ), 'UTF-8', 'UTF-16BE' );
			}

			if ( mb_check_encoding( $bytes, 'UTF-8' ) ) {
				return $bytes;
			}

			return (string) mb_convert_encoding( $bytes, 'UTF-8', 'Windows-1252' );
		}

		/**
		 * Collapses repeated blank lines/spaces produced by tag stripping.
		 *
		 * @param string $text Raw extracted text.
		 * @return string
		 */
		private static function clean_whitespace( $text ) {
			$text = (string) $text;
			$text = preg_replace( '/[ \t]+/', ' ', $text );
			$text = preg_replace( '/\n{3,}/', "\n\n", $text );

			return trim( $text );
		}

		/**
		 * Pulls the <title> out of an HTML document, if present.
		 *
		 * @param string $html Raw HTML.
		 * @return string
		 */
		private static function extract_html_title( $html ) {
			if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
				return trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
			}
			return '';
		}

		/**
		 * Best-effort, dependency-free text extraction for PDF files.
		 *
		 * PDFs store page text inside content streams as show-text operators
		 * (Tj / TJ) between BT...ET markers, optionally Flate (zlib)
		 * compressed. This decompresses each stream and pulls the literal
		 * strings out of those operators.
		 *
		 * Works well for standard text-based PDFs (the vast majority of
		 * exported docs/articles). Scanned or image-only PDFs have no text
		 * layer and will correctly return an empty string.
		 *
		 * @param string $bytes Raw PDF file contents.
		 * @return string
		 */
		private static function extract_pdf_text( $bytes ) {
			if ( '' === (string) $bytes ) {
				return '';
			}

			$text    = '';
			$offset  = 0;
			$streams = 0;
			$length  = strlen( $bytes );

			// Walk the file with strpos() instead of one big regex: no
			// backtracking, no PCRE limits, and a hard cap on work done.
			while ( $offset < $length && $streams < self::MAX_PDF_STREAMS ) {
				$pos = strpos( $bytes, 'stream', $offset );

				if ( false === $pos ) {
					break;
				}

				// Skip the "stream" inside the word "endstream".
				if ( $pos >= 3 && 'end' === substr( $bytes, $pos - 3, 3 ) ) {
					$offset = $pos + 6;
					continue;
				}

				$start = $pos + 6;

				if ( "\r" === substr( $bytes, $start, 1 ) ) {
					++$start;
				}
				if ( "\n" === substr( $bytes, $start, 1 ) ) {
					++$start;
				}

				$end = strpos( $bytes, 'endstream', $start );

				if ( false === $end ) {
					break;
				}

				$stream = substr( $bytes, $start, $end - $start );
				$offset = $end + 9;
				++$streams;

				// Some generators wrap Flate data in ASCII85 text.
				$trimmed = rtrim( $stream );
				if ( '~>' === substr( $trimmed, -2 ) ) {
					$a85 = self::ascii85_decode( $trimmed );
					if ( '' !== $a85 ) {
						$stream = $a85;
					}
				}

				// Bounded inflate: a tiny crafted stream cannot expand to gigabytes.
				$decoded = @gzuncompress( $stream, self::MAX_PDF_STREAM_BYTES ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- many streams aren't Flate-compressed; failure just means we try the raw bytes instead.
				$chunk   = false !== $decoded ? $decoded : $stream;

				if ( false === strpos( $chunk, 'BT' ) ) {
					continue;
				}

				$text .= self::extract_pdf_show_text_ops( $chunk ) . "\n";

				// Enough text already - stop doing work nobody will keep.
				if ( strlen( $text ) > self::MAX_CHARS_PER_ENTRY * 4 ) {
					break;
				}
			}

			return self::clean_whitespace( self::to_utf8( $text ) );
		}

		/**
		 * Extracts the string operands of Tj / TJ (show text) operators from
		 * a single decoded PDF content stream: literal strings (...) and
		 * hex strings <...>.
		 *
		 * @param string $stream Decoded PDF content stream.
		 * @return string
		 */
		private static function extract_pdf_show_text_ops( $stream ) {
			$out = array();

			// Tj: (literal) Tj, or <hex> Tj.
			if ( preg_match_all( '/(?:\(((?:[^()\\\\]|\\\\.)*)\)|<([0-9A-Fa-f\s]*)>)\s*Tj/s', $stream, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $match ) {
					$out[] = isset( $match[2] ) && '' !== $match[2] ? self::decode_pdf_hex( $match[2] ) : self::unescape_pdf_string( $match[1] );
				}
			}

			// TJ: [ (str) -120 <hex> ... ] TJ - numbers are kerning and ignored.
			if ( preg_match_all( '/\[((?:[^\[\]]|\\\\.)*)\]\s*TJ/s', $stream, $arrays ) ) {
				foreach ( $arrays[1] as $array_body ) {
					if ( preg_match_all( '/\(((?:[^()\\\\]|\\\\.)*)\)|<([0-9A-Fa-f\s]+)>/s', $array_body, $m2, PREG_SET_ORDER ) ) {
						$line = '';

						foreach ( $m2 as $match ) {
							$line .= isset( $match[2] ) && '' !== $match[2] ? self::decode_pdf_hex( $match[2] ) : self::unescape_pdf_string( $match[1] );
						}

						$out[] = $line;
					}
				}
			}

			return implode( ' ', $out );
		}

		/**
		 * Minimal ASCII85 decoder (PDF flavour) used for streams that are
		 * ASCII85-wrapped before Flate compression.
		 *
		 * @param string $data ASCII85 text ending in "~>".
		 * @return string Decoded bytes, or empty string on bad input.
		 */
		private static function ascii85_decode( $data ) {
			$data = preg_replace( '/\s+/', '', (string) $data );

			if ( 0 === strpos( $data, '<~' ) ) {
				$data = substr( $data, 2 );
			}

			$data  = substr( $data, 0, (int) strpos( $data, '~>' ) );
			$out   = '';
			$tuple = array();
			$len   = strlen( $data );

			for ( $i = 0; $i < $len; $i++ ) {
				$c = ord( $data[ $i ] );

				if ( 122 === $c && empty( $tuple ) ) { // 'z' = four zero bytes.
					$out .= "\0\0\0\0";
					continue;
				}

				if ( $c < 33 || $c > 117 ) {
					return '';
				}

				$tuple[] = $c - 33;

				if ( 5 === count( $tuple ) ) {
					$n     = ( ( ( $tuple[0] * 85 + $tuple[1] ) * 85 + $tuple[2] ) * 85 + $tuple[3] ) * 85 + $tuple[4];
					$out  .= chr( ( $n >> 24 ) & 255 ) . chr( ( $n >> 16 ) & 255 ) . chr( ( $n >> 8 ) & 255 ) . chr( $n & 255 );
					$tuple = array();
				}
			}

			$rest = count( $tuple );

			if ( $rest > 1 ) {
				for ( $j = $rest; $j < 5; $j++ ) {
					$tuple[] = 84;
				}

				$n    = ( ( ( $tuple[0] * 85 + $tuple[1] ) * 85 + $tuple[2] ) * 85 + $tuple[3] ) * 85 + $tuple[4];
				$raw  = chr( ( $n >> 24 ) & 255 ) . chr( ( $n >> 16 ) & 255 ) . chr( ( $n >> 8 ) & 255 ) . chr( $n & 255 );
				$out .= substr( $raw, 0, $rest - 1 );
			}

			return $out;
		}

		/**
		 * Decodes a PDF hex string. Two-byte values that look like
		 * UTF-16BE text (byte order mark present) are converted; plain
		 * one-byte strings are returned as bytes.
		 *
		 * Hex strings from fonts that use custom glyph encodings (CID
		 * fonts) are NOT text and cannot be read without the font's
		 * ToUnicode table, which this lightweight reader does not parse.
		 *
		 * @param string $hex Hex digits (whitespace allowed).
		 * @return string
		 */
		private static function decode_pdf_hex( $hex ) {
			$hex = preg_replace( '/\s+/', '', $hex );

			if ( '' === $hex ) {
				return '';
			}

			if ( strlen( $hex ) % 2 ) {
				$hex .= '0';
			}

			$raw = (string) hex2bin( $hex );

			if ( 0 === strpos( $raw, "\xFE\xFF" ) ) {
				return (string) mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16BE' );
			}

			// Only keep the result when it is plausible printable text.
			return preg_match( '/^[\x09\x0A\x0D\x20-\x7E\x80-\xFF]+$/', $raw ) ? $raw : '';
		}

		/**
		 * Resolves PDF literal-string escapes in ONE pass (so an escaped
		 * backslash followed by "n" is not turned into a newline):
		 * \n \r \t \b \f \( \) \\ and octal \ddd.
		 *
		 * @param string $str Raw PDF literal string contents.
		 * @return string
		 */
		private static function unescape_pdf_string( $str ) {
			return (string) preg_replace_callback(
				'/\\\\(?:([0-7]{1,3})|(.)|$)/s',
				function ( $m ) {
					if ( isset( $m[1] ) && '' !== $m[1] ) {
						return chr( octdec( $m[1] ) & 0xFF );
					}

					$map = array(
						'n' => "\n",
						'r' => "\n",
						't' => ' ',
						'b' => '',
						'f' => '',
					);

					if ( isset( $m[2] ) ) {
						return isset( $map[ $m[2] ] ) ? $map[ $m[2] ] : $m[2];
					}

					return '';
				},
				$str
			);
		}
	}
}
