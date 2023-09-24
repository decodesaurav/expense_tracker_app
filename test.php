<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'ENV' ) && ENV === true ) {
    ini_set( 'display_startup_errors', 1 );
    ini_set( 'display_errors', 1 );
    error_reporting( E_ALL );
}
// Exit if accessed directly
require_once 'OAuth.php';
require_once 'SingleVariationUploadManager.php';
if ( ! class_exists( 'ETCPF_EtsyUpload' ) ) {
    class ETCPF_EtsyUpload {

        public $list_prepared = 0;
        public $who_made;
        public $when_made;
        public $state;
        public $shipping_template_id;
        public $shop_id;
        private $listing = array();
        public $globalQuantity = null;
        public $uploadData;
        public $offeringValues;
        public $globalPrice = null;
        public $parent_quantity = null;
        public $sku = null;

        // Response Object
        public $variation_upload_message;
        public $failedReason;
        public $additionalMessages;
        public $etsy_api_limit = 0;
        public $listing_id;
        public $responseStatus;
        public $item_id;
        public $data;
        public $variation_result;
        public $imageId;
        public $images_left;

        public $api_key;
        public $secret_key;

        private $resposeData = array();
        private $feedID;

        public function __construct() {
            $this->get_settings();
            $this->etsy_api_limit = etcpf_get_etsy_settings( 'etsy_api_limit' );
            if ( get_option( 'last_api_hit_timestamp' ) ) {
                $temp      = get_option( 'last_api_hit_timestamp' );
                $datetime1 = new DateTime( $temp->date ); // start time
                // $now = new DateTime(date('y-m-d h:i:sa',strtotime('+12 hours')));//start time
                $now                  = new DateTime( date( 'y-m-d h:i:sa' ) ); // start time
                $interval             = $now->diff( $datetime1 );
                $differenceinHour     = $interval->h + ( $interval->days * 24 );
                $this->timedifference = $differenceinHour;
                if ( intval( $differenceinHour ) < 12 ) {
                    $this->etsy_api_limit = $this->etsy_api_limit / 2;
                } else {
                    $now = new DateTime( date( 'y-m-d h:i:sa' ) ); // start time
                    update_option( 'last_api_hit_timestamp', $now );
                    update_option( 'etsy_api_count', 0 );
                }
            } else {
                $now = new DateTime( date( 'y-m-d h:i:sa' ) ); // start time
                update_option( 'last_api_hit_timestamp', $now );
                $this->etsy_api_limit = $this->etsy_api_limit / 2;
            }
            $this->shop_id            = get_option( 'etcpf_shop_id' );
            $this->api_key            = get_option( 'etcpf_api_key' );
            $this->secret_key         = get_option( 'etcpf_secret_key' );
            $this->oauth_token        = get_option( 'etcpf_oauth_token' );
            $this->oauth_token_secret = get_option( 'etcpf_oauth_token_secret' );
        }

        protected function _curlRequest( $request ) {
            $response = wp_remote_get(
                $request,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                )
            );
            $httpCode = wp_remote_retrieve_response_code( $response );
            $response = wp_remote_retrieve_body( $response );

            if ( $httpCode != 200 ) {
                echo "Return code is {$httpCode} \n"
                    . 'Response: ' . $response;
            }

            return $response;
        }

        public function countEtsyProduct() {
            global $wpdb;
            $etsyDetails = array();
            $url         = 'https://openapi.etsy.com/v3/application/shops/' . $this->shop_id . '/listings';

            // active product
            $results = $this->_curlGetRequestV3( $url );
            if ( $results ) {
                if ( isset( $results['count'] ) ) {
                    $etsyDetails['active'] = $results['count'];
                }
            }

            // draft product
            $params    = array( 'state' => 'draft' );
            $draft_url = $url . '?' . http_build_query( $params );
            $results   = $this->_curlGetRequestV3( $draft_url );
            if ( $results ) {
                if ( isset( $results['count'] ) ) {
                    $etsyDetails['draft'] = $results['count'];
                }
            }

            // inactive product
            $params       = array( 'state' => 'inactive' );
            $inactive_url = $url . '?' . http_build_query( $params );
            $results      = $this->_curlGetRequestV3( $inactive_url );
            if ( $results ) {
                if ( isset( $results['count'] ) ) {
                    $etsyDetails['inactive'] = $results['count'];
                }
            }

            return $etsyDetails;
        }

        public function findAllShopListingsActive( $startValue, $shop, $quantity = 25 ) {
            global $wpdb;
            $table = $wpdb->prefix . 'etcpf_etsy_sync';
            if ( $startValue == 0 ) {
                $page = 1;
            }
            if ( $startValue > 0 ) {
                $page = (int) ( $startValue / $quantity ) + 1;
            }

            $limit  = $quantity;
            $params = array(
                'offset' => $startValue,
                'limit'  => $limit,
                'page'   => $page,
            );
            $url    = 'https://openapi.etsy.com/v3/application/shops/' . $this->shop_id . '/listings?' . http_build_query( $params );
            $data   = json_encode( $this->_curlGetRequestV3( $url ) );

            if ( strpos( $data, 'does not exist' ) !== false ) {
                // No listing found indicate as job finish
                return true;
            } else {
                $results = json_decode( $data );
                if ( is_object( $results ) && $results->count > 0 ) {
                    $activeListing = $results->results;
                    foreach ( $activeListing as $key => $listings ) {
                        $listing_id   = $listings->listing_id;
                        $title        = $listings->title;
                        $sku          = ! empty( $listings->skus ) ? $listings->skus[0] : '';
                        $quantity     = $listings->quantity;
                        $state        = $listings->state;
                        $prepare_data = json_encode( $listings );

                        $post_data = array(
                            'listing_id'   => $listing_id,
                            'title'        => $title,
                            'sku'          => $sku,
                            'quantity'     => $quantity,
                            'state'        => $state,
                            'prepare_data' => $prepare_data,
                        );

                        $result = $wpdb->get_row(
                            $wpdb->prepare( "SELECT * FROM $table WHERE listing_id=%d AND state=%s", $listing_id, $state )
                        );
                        if ( $result ) {
                            $wpdb->update( $table, $post_data, array( 'listing_id' => $listing_id ) );
                        } else {
                            $wpdb->insert( $table, $post_data );
                        }
                    }
                    $startValue += $quantity;
                    // Indicate there are no product anymore
                    if ( is_null( $results->pagination->next_page ) ) {
                        return true;
                    }
                } else {
                    if ( $results->count == 0 ) {
                        // No listing found indicate as job finish
                        return true;
                    }
                }
            }
        }

        public function findAllShopListingsDraft( $startValue, $shop, $quantity = 25 ) {
            global $wpdb;
            $table = $wpdb->prefix . 'etcpf_etsy_sync';
            if ( $startValue == 0 ) {
                $page = 1;
            }
            if ( $startValue > 0 ) {
                $page = (int) ( $startValue / $quantity ) + 1;
            }

            $limit  = $quantity;
            $params = array(
                'state'  => 'draft',
                'offset' => $startValue,
                'limit'  => $limit,
                'page'   => $page,
            );
            $url    = 'https://openapi.etsy.com/v3/application/shops/' . $this->shop_id . '/listings?' . http_build_query( $params );

            $data = json_encode( $this->_curlGetRequestV3( $url ) );

            if ( strpos( $data, 'does not exist' ) !== false ) {
                // No listing found indicate as job finish
                return true;
            } else {
                $results = json_decode( $data );
                if ( is_object( $results ) && $results->count > 0 ) {
                    $activeListing = $results->results;
                    foreach ( $activeListing as $key => $listings ) {
                        $listing_id   = $listings->listing_id;
                        $title        = $listings->title;
                        $sku          = ! empty( $listings->skus ) ? $listings->skus[0] : '';
                        $quantity     = $listings->quantity;
                        $state        = $listings->state;
                        $prepare_data = json_encode( $listings );

                        $post_data = array(
                            'listing_id'   => $listing_id,
                            'title'        => $title,
                            'sku'          => $sku,
                            'quantity'     => $quantity,
                            'state'        => $state,
                            'prepare_data' => $prepare_data,
                        );

                        $result = $wpdb->get_row(
                            $wpdb->prepare( "SELECT * FROM $table WHERE listing_id=%d AND state=%s", $listing_id, $state )
                        );
                        if ( $result ) {
                            $wpdb->update( $table, $post_data, array( 'listing_id' => $listing_id ) );
                        } else {
                            $wpdb->insert( $table, $post_data );
                        }
                    }
                    $startValue += $quantity;
                    // Indicate there are no product anymore
                    if ( is_null( $results->pagination->next_page ) ) {
                        return true;
                    }
                } else {
                    if ( $results->count == 0 ) {
                        // No listing found indicate as job finish
                        return true;
                    }
                }
            }
        }

        public function findAllShopListingsInactive( $startValue, $shop, $quantity = 25 ) {
            global $wpdb;
            $table = $wpdb->prefix . 'etcpf_etsy_sync';
            if ( $startValue == 0 ) {
                $page = 1;
            }
            if ( $startValue > 0 ) {
                $page = (int) ( $startValue / $quantity ) + 1;
            }

            $limit  = $quantity;
            $params = array(
                'state'  => 'inactive',
                'offset' => $startValue,
                'limit'  => $limit,
                'page'   => $page,
            );
            $url    = 'https://openapi.etsy.com/v3/application/shops/' . $this->shop_id . '/listings?' . http_build_query( $params );
            $data   = json_encode( $this->_curlGetRequestV3( $url ) );

            if ( strpos( $data, 'does not exist' ) !== false ) {
                // No listing found indicate as job finish
                return true;
            } else {
                $results = json_decode( $data );
                if ( is_object( $results ) && $results->count > 0 ) {
                    $activeListing = $results->results;
                    foreach ( $activeListing as $key => $listings ) {
                        $listing_id   = $listings->listing_id;
                        $title        = $listings->title;
                        $sku          = ! empty( $listings->skus ) ? $listings->skus[0] : '';
                        $quantity     = $listings->quantity;
                        $state        = $listings->state;
                        $prepare_data = json_encode( $listings );

                        $post_data = array(
                            'listing_id'   => $listing_id,
                            'title'        => $title,
                            'sku'          => $sku,
                            'quantity'     => $quantity,
                            'state'        => $state,
                            'prepare_data' => $prepare_data,
                        );

                        $result = $wpdb->get_row(
                            $wpdb->prepare( "SELECT * FROM $table WHERE listing_id=%d AND state=%s", $listing_id, $state )
                        );
                        if ( $result ) {
                            $wpdb->update( $table, $post_data, array( 'listing_id' => $listing_id ) );
                        } else {
                            $wpdb->insert( $table, $post_data );
                        }
                    }
                    $startValue += $quantity;
                    // Indicate there are no product anymore
                    if ( is_null( $results->pagination->next_page ) ) {
                        return true;
                    }
                } else {
                    if ( $results->count == 0 ) {
                        // No listing found indicate as job finish
                        return true;
                    }
                }
            }
        }

        public function mapEtsyProducts() {
            global $wpdb;
            $etsySyncTable = $wpdb->prefix . 'etcpf_etsy_sync';
            $productTable  = $wpdb->prefix . 'etcpf_listings';

            $result = $wpdb->get_row( "SELECT sku,id FROM $etsySyncTable WHERE mapped_status=0 " );
            if ( ! $result ) {
                return false;
            }

            $sku      = ! empty( $result->sku ) ? $result->sku : '';
            $id       = ! empty( $result->id ) ? $result->id : ''; // Primary Key
            $response = array();
            if ( ! empty( $sku ) ) {
                // Start mapping
                $data = $wpdb->get_row(
                    $wpdb->prepare( "SELECT * FROM $productTable WHERE sku=%s", $sku )
                );
                if ( ! empty( $data ) ) {
                    $response['error_status']  = 0;
                    $response['message']       = 'Product Mapped';
                    $response['sku']           = $sku;
                    $response['product_id']    = $id;
                    $response['mapped_status'] = 1;
                } else {
                    // Product not found
                    $response['error_status']  = 1;
                    $response['message']       = 'No SKU found. Failed to map';
                    $response['product_id']    = null;
                    $response['mapped_status'] = 2;
                }
            } else {
                $response['error_status']  = 1;
                $response['message']       = 'SKU from Etsy not found. Failed to map';
                $response['product_id']    = null;
                $response['mapped_status'] = 2;
            }
            $newData = array(
                'message'       => $response['message'],
                'error_status'  => $response['error_status'],
                'mapped_status' => $response['mapped_status'],
                'product_id'    => $response['product_id'],
            );
            $wpdb->update( $etsySyncTable, $newData, array( 'id' => $id ) );
            $response_data = array(
                'message' => $response['message'],
                'id'      => $id,
            );

            return $response_data;
        }

        public function prepareHash( $url, $type, $put = false, $actualparams = false ) {
            $api_key            = get_option( 'etcpf_api_key' );
            $secret_key         = get_option( 'etcpf_secret_key' );
            $oauth_token        = get_option( 'etcpf_oauth_token' );
            $oauth_token_secret = get_option( 'etcpf_oauth_token_secret' );
            $hmac_method        = new ETCPF_OAuthSignatureMethod_HMAC_SHA1();
            $consumer           = new ETCPF_OauthConsumer( $api_key, $secret_key );
            $token              = new ETCPF_OauthConsumer( $oauth_token, $oauth_token_secret );

            if ( $put ) {
                $params = array( 'method' => 'PUT' );
            } else {
                $params = array( 'method' => $type );
            }
            if ( $actualparams !== false ) {
                $params = array_merge( $params, $actualparams );
            }

            $acc_req = ETCPF_OAuthRequest::from_consumer_and_token( $consumer, $token, $type, $url, $params );
            $acc_req->sign_request( $hmac_method, $consumer, $token );

            return $acc_req;
        }

        public function MultipartHash( $url, $type ) {

            $api_key             = '1nm4z285gajxmpygv3pja7ik';
            $secret_key          = 'lzl2rfq7s3';
            $oauth_token         = get_option( 'etcpf_oauth_token' );
            $oauth_token_secret  = get_option( 'etcpf_oauth_token_secret' );
            $access_token        = 'e42ee350c0df731fd191889408dc3c'; // get from db
            $access_token_secret = '5e60790707'; // get from db

            $hmac_method = new ETCPF_OAuthSignatureMethod_HMAC_SHA1();
            $consumer    = new ETCPF_OauthConsumer( $api_key, $secret_key );
            $token       = new ETCPF_OauthConsumer( $access_token, $access_token_secret );

            $params  = array( 'method' => $type );
            $acc_req = ETCPF_OAuthRequest::from_consumer_and_token( $consumer, $token, $type, $url, $params );
            $acc_req->sign_request( $hmac_method, $consumer, $token );

            return $acc_req;
        }

        public function prepare_the_list_from_feed( $id, $cron = false ) {
            $resubmit = false;
            if ( isset( $_REQUEST['resubmit'] ) ) {
                $resubmit = true;
            }
            $result = null;
            global $wpdb;
            $tbl = $wpdb->prefix . 'etcpf_feeds';
            $url = $wpdb->get_var( $wpdb->prepare( "SELECT url FROM $tbl WHERE id = %d", array( $id ) ) );
            $wpdb->update( $tbl, array( 'feed_title' => 'uploading' ), array( 'id' => $id ) );
            if ( ! $url ) {
                return false;
            }
            $upload_dir   = wp_upload_dir();
            $dir          = $upload_dir['basedir'] . '/etsy_merchant_feeds/Etsy/';
            $url          = $dir . basename( $url );
            $feed_content = json_decode( json_encode( simplexml_load_file( $url, 'SimpleXMLElement', LIBXML_NOCDATA ) ) );
            $listings     = $feed_content->channel->item;
            if ( is_countable( $listings ) ) {
                if ( is_array( $listings ) && count( $listings ) == 0 ) {
                    // error_log("listing copunt zero");
                    return true;
                } elseif ( ( is_array( $listings ) || is_object( $listings ) ) && @count( $listings ) == 1 ) {
                    $listings = array( $feed_content->channel->item );
                }
            } elseif ( count( get_object_vars( $listings ) ) == 0 ) {
                return true;
            } elseif ( is_object( $listings ) ) {
                $listings = array( $feed_content->channel->item );
            }

            update_option( 'currently_uploading_feed_id', $id );
            // error_log("prepareing listing method");
            foreach ( $listings as $key => $listing ) {
                if ( ! empty( $listing->item_group_id ) ) {
                    $result = $this->insertVariation( $listing->item_group_id, $listing, $id, $resubmit );
                } else {
                    $table = $wpdb->prefix . 'etcpf_listing_variations';
                    $wpdb->delete( $table, array( 'parent_id' => $listing->id ) );
                    $result = $this->insertListings( $listing, $id, $resubmit, $cron );
                }
            }
            if ( $result ) {
                return true;
            }

            return false;
        }

        public function insertListings( $listing, $itemid, $resubmit, $cron ) {
            global $wpdb;
            $table           = $wpdb->prefix . 'etcpf_listings';
            $failedupload    = isset( $_REQUEST['uploadfailed'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['uploadfailed'] ) ) : false;
            $preparedListing = $this->prepareData( $listing );
            // $checkSync = $this->checkForSyncProduct($preparedListing['sku']);
            // echo '<pre>';
            // print_r($checkSync);
            // echo '</pre>';
            $datatobeinserted = array(
                'item_id'       => $listing->id,
                'title'         => $preparedListing['title'],
                'has_variation' => isset( $listing->has_variation ) ? $listing->has_variation : '0',
                'feed_id'       => $itemid,
                'data'          => maybe_serialize( $listing ),
                'prepared_data' => json_encode( $preparedListing ),
                'sku'           => $preparedListing['sku'],
            );
            if ( $data = $this->checkIFInserted( $listing->id, $table ) ) {
                // Now it should be handled by productlistw
                /*
                if (etcpf_get_etsy_settings('state') === 'draft' && $preparedListing['state']=='draft') {
                    $etsyListingstate = $this->checkStatusInEtsy($data->listing_id);
                    if($etsyListingstate){
                        //$previousData = json_decode($data->prepared_data);
                        $preparedListing['state'] = $etsyListingstate;
                        $datatobeinserted['prepared_data'] = json_encode($preparedListing);
                    }
                }*/
                if ( $failedupload == false && $resubmit == false && $cron == true ) {
                    $datatobeinserted['uploaded'] = 0; /* If listing is not saved and marked as uploaded, change it to unuploaded */
                } elseif ( $failedupload == false && $resubmit == true ) {
                    $datatobeinserted['uploaded'] = 7; /* Flag for resubmit */
                } elseif ( $failedupload == false && $resubmit == false && $data->uploaded == 7 ) {
                    $datatobeinserted['uploaded'] = 0;
                } elseif ( $failedupload == false && $resubmit == false && $data->uploaded == 3 ) {
                    $datatobeinserted['uploaded'] = 0;
                }
                if ( $wpdb->update( $table, $datatobeinserted, array( 'item_id' => $listing->id ) ) ) {
                    return true;
                }

                return false;
            } else {
                if ( $resubmit == true ) {
                    $datatobeinserted['uploaded'] = 7;
                } else {
                    $datatobeinserted['uploaded'] = 0;
                }
                $datatobeinserted['item_group_id']           = null;
                $datatobeinserted['variation_upload_result'] = null;
                $datatobeinserted['error']                   = 'empty';
                if ( $wpdb->insert( $table, $datatobeinserted ) ) {
                    return true;
                }

                return false;
            }
        }

        public function insertVariation( $item_group_id, $listing, $id, $resubmit ) {
            /*
            =====================================================
                             *For preparing variation listings from feed file*
            */
            global $wpdb;
            $table            = $wpdb->prefix . 'etcpf_listing_variations';
            $datatobeinserted = array(
                'item_id'   => $listing->id,
                'parent_id' => $item_group_id,
                'data'      => maybe_serialize( $listing ),
                'submitted' => date( 'Y-m-d h:i:sa' ),
                'updated'   => date( 'Y-m-d h:i:sa' ),
            );
            /*
            if ($data = $this->checkIFInserted($listing->id, $table)) {
                if ($wpdb->update($table, $datatobeinserted, array('item_id' => $listing->id))) {
                    return true;
                }
                return false;
            } else {*/
            $datatobeinserted['upload'] = 0;
            if ( $wpdb->insert( $table, $datatobeinserted ) ) {
                return true;
            }

            return false;
            // }
            // return true;
        }

        public function checkIFInserted( $id, $listingtable ) {
            global $wpdb;
            $sql    = $wpdb->prepare( 'SELECT * FROM ' . $listingtable . ' WHERE item_id=%d', array( $id ) );
            $result = $wpdb->get_row( $sql );

            return $result;
        }

        public function prepare_the_list_by_id( $id, $params ) {
            global $wpdb;
            $tbl = $wpdb->prefix . 'etcpf_listings';
            $sql = "SELECT * FROM $tbl WHERE id = $id";
            $row = $wpdb->get_row( $sql, ARRAY_A );
            // $data = maybe_unserialize($row['data']);
            $preparedData = json_decode( $row['prepared_data'] );
            foreach ( $params as $key => $value ) {
                // $data->$key = $value;
                $preparedData->$key = $value;
            }
            $row['prepared_data'] = json_encode( $preparedData );
            // $row['data'] = maybe_serialize($data);
            // $wpdb->update($tbl, array('data' => maybe_serialize($data),'prepared_data'=>json_encode($preparedData), 'uploaded' => 0), array('id' => $id));
            return $row;
        }

        public function product_already_uploaded( $id, $item_group_id ) {
            global $wpdb;
            $tbl     = $wpdb->prefix . 'etcpf_listings';
            $sql     = $wpdb->prepare( "SELECT id FROM $tbl WHERE item_id = %d OR item_group_id=%d", array(
                $id,
                $id
            ) );
            $knownID = $wpdb->get_var( $sql );
            if ( $knownID > 0 ) {
                return $knownID;
            } else {
                $sql     = $wpdb->prepare( "SELECT id FROM $tbl WHERE item_id = %d OR item_group_id=%d", array(
                    $item_group_id,
                    $item_group_id
                ) );
                $knownID = $wpdb->get_var( $sql );

                return $knownID;
            }

            return false;
        }

        public function get_unsubmitted_listing( $itemid, $type ) {
            global $wpdb;
            $table = $wpdb->prefix . 'etcpf_listings';
            // $listing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE feed_id = %d AND uploaded=%d AND etsy_status != %s ORDER BY id ASC", array($itemid, $type, 'removed')), ARRAY_A);
            $listing = $wpdb->get_row( "SELECT * FROM $table WHERE id = {$itemid} ORDER BY id ASC ", ARRAY_A );

            if ( empty( $listing ) || ( is_array( $listing ) && count( $listing ) == 0 ) ) {
                $test = $wpdb->get_results( "SELECT * FROM $table WHERE feed_id = {$itemid} ORDER BY id ASC ", ARRAY_A );

                return false;
            }

            $data = array( 'uploaded' => 4 );
            $wpdb->update( $table, $data, array( 'id' => $listing['id'] ) );

            return $listing;
        }

        public function check_failed_listing( $where = 0 ) {
            global $wpdb;
            $table   = $wpdb->prefix . 'etcpf_listings';
            $listing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE feed_id = %d AND uploaded=%d", array(
                $where,
                3
            ) ), ARRAY_A );
            if ( empty( $listing ) || ( is_array( $listing ) && count( $listing ) == 0 ) ) {
                return false;
            }
            $data = array(
                'uploaded' => 4,
            );
            $wpdb->update( $table, $data, array( 'id' => $listing['id'] ) );

            return $listing;
        }

        public function check_resubmit_listing( $where = 0 ) {
            global $wpdb;
            $table   = $wpdb->prefix . 'etcpf_listings';
            $listing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE feed_id = %d AND uploaded=%d", array(
                $where,
                7
            ) ), ARRAY_A );
            if ( empty( $listing ) || ( is_array( $listing ) && count( $listing ) == 0 ) ) {
                return false;
            }
            $data = array(
                'uploaded' => 4,
            );
            $wpdb->update( $table, $data, array( 'id' => $listing['id'] ) );

            return $listing;
        }

        public function get_offering_prices_from_etsy( $listing_id ) {
            $url = 'https://openapi.etsy.com/v3/application/listings/' . $listing_id . '/inventory';

            $response = wp_remote_get(
                $url,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'Expect'        => '',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer ' . get_option( 'etcpf_oauth_token' )
                    ),
                )
            );
            if ( wp_remote_retrieve_response_code( $response ) == 200 || wp_remote_retrieve_response_code( $response ) == 201 ) {
                $result   = json_decode( wp_remote_retrieve_body( $response ) );
                $products = $result->products;
                if ( ! empty( $products ) ) {
                    foreach ( $products as $key => $product ) {
                        $price_from_etsy = $product->offerings[0]->price;
                        $price_array_key = '';
                        foreach ( $product->property_values as $key => $property_value ) {
                            if ( $price_array_key !== '' ) {
                                $price_array_key .= '_-_';
                            }
                            $price_array_key .= $property_value->values[0];
                        }
                        if ( ! empty( $price_from_etsy ) ) {
                            $price_array[ $price_array_key ] = strval( $price_from_etsy->amount / $price_from_etsy->divisor );
                        }
                    }

                    return $price_array;
                } else {
                    return false;
                }
            }

            return false;
        }

        public function get_listing_price_from_etsy( $listing_id ) {
            $url = 'https://openapi.etsy.com/v3/application/listings/' . $listing_id . '/inventory';

            $response = wp_remote_get(
                $url,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'Expect'        => '',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer ' . get_option( 'etcpf_oauth_token' )
                    ),
                )
            );
            if ( wp_remote_retrieve_response_code( $response ) == 200 || wp_remote_retrieve_response_code( $response ) == 201 ) {
                $result  = json_decode( wp_remote_retrieve_body( $response ) );
                $product = $result->products[0];
                if ( ! empty( $product ) ) {
                    return $product;
                } else {
                    return false;
                }
            }

            return false;
        }

        public function is_product_in_stock( $product_id ) {
            $total_stock = $this->get_stock_of_a_product( $product_id );
            if ( $total_stock > 0 ) {
                return true;
            } else {
                return false;
            }
        }

        public function get_stock_of_a_product( $product_id ) {
            $product     = wc_get_product( $product_id );
            $total_stock = $product->get_stock_quantity();
            if ( $total_stock > 0 && ( $total_stock < 999 ) ) {
                return $total_stock;
            } elseif ( $total_stock > 0 && ( $total_stock > 999 ) ) {
                return 999;
            } elseif ( $product->get_manage_stock() === false && $product->get_stock_status() === 'instock' ) {
                return 10;
            } else {
                return 0;
            }
        }


        public function submit_listing_to_etsy( $itemid, $type, $feed_id, $essetials ) {
            $first_upload = false;
            $this->feedID = $feed_id;
            /** Checking if the api limit is reached */
            if ( get_option( 'etsy_api_count' ) ) {
                $current_api_hit = get_option( 'etsy_api_count' );
            } else {
                add_option( 'etsy_api_count' );
                $current_api_hit = 0;
            }
            /*Api limit check finished*/

            if ( intval( $current_api_hit ) < intval( $this->etsy_api_limit ) ) {
                $product_upload_count = get_option( 'etsy_product_upload_count' );
                if ( ! $product_upload_count ) {
                    add_option( 'etsy_product_upload_count', 0 );
                    $product_upload_count = 0;
                }

                if ( function_exists( 'efwpte_fs' ) ) {
                    $fs = efwpte_fs();
                } else {
                    global $efwpte_fs;
                    $fs = $efwpte_fs;
                }

                $product_upload_limit = get_option( 'etsy_free_plan_product_upload_limit' );
                $product_upload_limit = $product_upload_limit ? $product_upload_limit : 20;

                if ( $fs->is_paying__premium_only() ) {
                    $product_upload_limit = 'unlimited';
                }

                if ( $product_upload_limit == 'unlimited' || $product_upload_limit > $product_upload_count ) {
                    $rawData = $this->get_unsubmitted_listing( $itemid, $type );
                    // $this->get_variation($rawData['item_id']);
                    if ( $rawData ) {
                        $etsyData = json_decode( $rawData['prepared_data'], true );
                        $InfoData = maybe_unserialize( $rawData['data'] );

                        // response materials
                        $this->resposeData['id']      = $rawData['id'];
                        $this->resposeData['item_id'] = $rawData['item_id'];
                        $this->resposeData['data']    = $rawData;

                        if ( $rawData['error'] == 'empty' || empty( $rawData['error'] ) ) {
                            if ( $rawData['listing_id'] === false || $rawData['listing_id'] === null || empty( $rawData['listing_id'] ) ) {
                                $first_upload    = true;
                                $updatetask      = false;
                                $submissionCheck = $this->submissionCheck( $rawData['sku'] );
                                if ( $submissionCheck ) {
                                    $rawData['listing_id'] = $submissionCheck;
                                    if ( ! empty( $rawData['has_variation'] ) ) {
                                        unset( $etsyData['price'] );
                                        unset( $etsyData['quantity'] );
                                    }
                                    $etsyListingstate = $this->stateResolver( $rawData['listing_id'], $rawData['item_id'] );
                                    if ( $etsyListingstate ) {
                                        $etsyData['state'] = $etsyListingstate;
                                    }
                                    $updatetask = true;
                                }
                                $this->updateApiLimitVal( get_option( 'etsy_api_count' ) );
                                $result = $this->_CURLFORUPLOAD( $rawData['listing_id'], $updatetask, $etsyData, $rawData['id'], true );

                            } else {
                                /* @INFO: Etsy doesn't supports price and quantity in parent product if it has variation uploaded */
                                if ( ! empty( $rawData['has_variation'] ) ) {
                                    unset( $etsyData['price'] );
                                    unset( $etsyData['quantity'] );
                                }

                                if ( etcpf_get_etsy_settings( 'price_sync' ) == 'no' && empty( $rawData['has_variation'] ) ) {
                                    $price = $this->get_listing_price_from_etsy( $rawData['listing_id'] );
                                    if ( $price ) {
                                        $etsyData['price'] = $price;
                                    }
                                }

                                if ( etcpf_get_etsy_settings( 'title_sync' ) == 'no' ) {
                                    unset( $etsyData['title'] );
                                }

                                if ( etcpf_get_etsy_settings( 'description_sync' ) == 'no' ) {
                                    unset( $etsyData['description'] );
                                }

                                if ( etcpf_get_etsy_settings( 'tags_sync' ) == 'no' ) {
                                    unset( $etsyData['tag'] );
                                }

                                if ( etcpf_get_etsy_settings( 'materials_sync' ) == 'no' ) {
                                    unset( $etsyData['materials'] );
                                }

                                $etsyListingstate = $this->stateResolver( $rawData['listing_id'], $rawData['item_id'] );
                                if ( $etsyListingstate ) {
                                    $etsyData['state'] = $etsyListingstate;
                                }
                                $updatetask = true;
                                $this->updateApiLimitVal( get_option( 'etsy_api_count' ) );
                                $result = $this->_CURLFORUPLOAD( $rawData['listing_id'], $updatetask, $etsyData, $rawData['id'], true );

                            }

                            $this->resposeData['listing_id'] = $result;

                            if ( empty( $rawData['has_variation'] ) ) {
                                $product = $this->get_etsy_products( $result );
                                $product = (array) $product->products[0];
                                $data = array(
                                    array(
                                        'price' => $etsyData['price'],
                                        "quantity" => $etsyData['quantity'],
                                        "is_enabled" => $product['offerings'][0]->is_enabled
                                    ),
                                );
                                $tempData['offerings'] = $data;
                                $tempData['sku'] = $etsyData['sku'];
                                $productData['products'] = array($tempData);
                                $data         =  $productData;

                                if ( ! is_null( $product ) ) {
                                    $this->variationUpload( $data, $result );
                                }
                            }

                            $this->resposeData['failed_reason'] = $this->failedReason;

                            /* After Uploading main product, uploadiong variations now*/
                            if ( $result ) {
                                if ( get_option( 'etsy_api_count' ) < $this->etsy_api_limit ) {

                                    if ( isset( $InfoData->primary_color ) || isset( $InfoData->color ) ) {

                                        $InfoData->primary_color = isset( $InfoData->primary_color ) ? $InfoData->primary_color : $InfoData->color;
                                        if ( $etsyvaluesid = $this->changeColorstoEtsycolorcodes( $InfoData->primary_color ) ) {

                                            $this->updateApiLimitVal( get_option( 'etsy_api_count' ) );
                                            $primarycolorpush = $this->uploadColorTypes( $result, $updatetask, $etsyvaluesid, '200' );
                                            if ( $primarycolorpush === false ) {
                                                $this->additionalMessages = $primarycolorpush;
                                            }
                                        }
                                    }

                                    // Uploading Image for etsy
                                    $overwrite         = 1;
                                    $upload_additional = true;
                                    if ( $rawData['listing_image_id'] ) {
                                        if ( $type == 7 ) {
                                            $upload_additional = true;
                                        }
                                        // $imageupload = $rawData['listing_image_id'];
                                        if ( etcpf_get_etsy_settings( 'images_sync' ) === 'yes' ) {
                                            $imageupload = $this->submit_listing_images( $result, $rawData['id'], $rawData['item_id'], $InfoData->image_link, $overwrite, $upload_additional );
                                        } else {
                                            $imageupload = $rawData['listing_image_id'];
                                        }
                                    } else {
                                        if ( isset( $InfoData->image_link ) ) {
                                            $imageupload = $this->submit_listing_images( $result, $rawData['id'], $rawData['item_id'], $InfoData->image_link, $overwrite, true );
                                        } else {
                                            $imageupload = null;
                                        }
                                    }

                                    if ( ! empty( $InfoData->has_variation ) ) {
                                        // Uploading Variation
                                        if ( $essetials['uploadType'] === 'single' ) {
                                            if ( empty( $essetials['variation_profile'] ) ) {
                                                return array(
                                                    'status'  => 'error',
                                                    'message' => 'Profile Not selected for single variation',
                                                );
                                            }
                                            $variation_data = $this->testSinglyVariatedFormat( $rawData['item_id'], $feed_id, $essetials['variation_profile'] );
                                        } else {
                                            $variations     = $this->get_variation_from_feed( $rawData['item_id'] );
                                            $variation_data = $this->getpreparedVariationDataforEtsy( $variations, $result, $first_upload );
                                        }
                                        if ( is_array( $variation_data ) && count( $variation_data ) > 0 ) {
                                            $varationUploadresult = $this->variationUpload( $variation_data, $result );
                                            if ( $essetials['uploadType'] === 'single' || count($variation_data['products']) === 1 ) {
                                                $this->variationImageManagement( $varationUploadresult, $result );
                                            }
                                            if ( $varationUploadresult ) {
                                                $this->resposeData['variation_result'] = $this->variation_upload_message;
                                            } else {
                                                $this->resposeData['variation_result'] = $this->variation_upload_message;
                                            }
                                        } else {
                                            $this->resposeData['variation_result'] = $variation_data;
                                        }
                                    } else {

                                        $this->resposeData['variation_result'] = false;
                                    }


                                    update_option( 'etsy_product_upload_count', ( intval( $product_upload_count ) + 1 ));

                                    $this->resposeData['status']     = 'CONTINUE';
                                    $this->resposeData['image_id']   = $imageupload;
                                    $this->resposeData['updatetask'] = $updatetask;

                                } else {

                                    $this->resposeData['status']  = 'HAULT';
                                    $this->resposeData['message'] = 'The etsy Api Limit exceeded.';
                                    $this->resposeData['time']    = $this->timedifference;

                                }
                            } else {

                                if ( $type == 3 ) {
                                    $this->makelistingUnuploaded( $itemid, $rawData['id'], $type );
                                }
                                $this->resposeData['status']        = 'CONTINUE';
                                $this->resposeData['failed_reason'] = $this->failedReason;
                                $this->resposeData['time']          = $this->timedifference;

                            }
                        } else {

                            $this->resposeData['status']  = 'CONTINUE';
                            $this->resposeData['message'] = $rawData['error'];
                            $this->resposeData['time']    = $this->timedifference;

                        }
                    } else {
                        $this->resposeData['status'] = 'FINISHED';
                    }
                } else {
                    $this->resposeData['status']  = 'UPGRADE';
                    $this->resposeData['message'] = 'Product upload limit exceeded.';
                }
            } else {

                $this->resposeData['status']  = 'HAULT';
                $this->resposeData['message'] = 'The etsy Api Limit exceeded.';
                $this->resposeData['time']    = $this->timedifference;

            }

            return $this->resposeData;

        }

        public function process_submit_listing_to_etsy() {

        }

        public function UpoadListingByID( $id ) {
            $rawData = $this->getListingById( $id );

            return $this->EtsyUploadById( $rawData, false );
        }

        public function get_etsy_products( $listing_id ) {

            $url              = 'https://openapi.etsy.com/v3/application/listings/' . intval( $listing_id ) . '/inventory';
            $acc_req          = $this->prepareHash( $url, 'GET', false );
            $response         = wp_remote_get(
                $acc_req,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type' => 'application/json',
                        'Expect'       => '',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer '
                            . get_option( 'etcpf_oauth_token' )
                    ),
                )
            );
            $decoded_response = json_decode( wp_remote_retrieve_body( $response ) );
            if ( isset( $decoded_response->products ) ) {
                return $decoded_response;
            }

            return null;
        }

        public function EtsyUploadById( $rawData, $update = false ) {
            if ( $rawData ) {
                $first_upload = ( ! isset( $rawData['listing_id'] ) || $update == false ) ? true : false;
                $etsyData     = json_decode( $rawData['prepared_data'], true );
                $InfoData     = maybe_unserialize( $rawData['data'] );

                // response materials
                $this->resposeData['id']      = $rawData['id'];
                $this->resposeData['item_id'] = $rawData['item_id'];
                $this->resposeData['data']    = $rawData;

                if ( $rawData['error'] == 'empty' || empty( $rawData['error'] ) ) {
                    $this->updateApiLimitVal( get_option( 'etsy_api_count' ) );
                    if ( $update == true ) {
                        $updatetask = true;
                        if ( isset( $rawData['listing_id'] ) ) {
                            if ( $rawData['has_variation'] ) {
                                unset( $etsyData['quantity'] );
                                unset( $etsyData['price'] );
                            }
                            $etsyListingstate = $this->stateResolver( $rawData['listing_id'], $rawData['item_id'] );
                            if ( $etsyListingstate ) {
                                $etsyData['state'] = $etsyListingstate;
                            }
                            $result = $this->_CURLFORUPLOAD( $rawData['listing_id'], $updatetask, $etsyData, $rawData['id'], true );
                        } else {
                            $result = $this->_CURLFORUPLOAD( $rawData['listing_id'], false, $etsyData, $rawData['id'], true );
                        }
                    } else {
                        $updatetask            = false;
                        $etsy_state            = etcpf_get_etsy_settings( 'state' );
                        $etsyData['state']     = $etsy_state;
                        $rawData['etsy_state'] = $etsy_state;
                        $result                = $this->_CURLFORUPLOAD( '', $updatetask, $etsyData, $rawData['id'], true );
                    }
                    $this->resposeData['listing_id']    = $result;
                    $this->resposeData['failed_reason'] = $this->failedReason;

                    /* After Uploading main product, uploadiong variations now*/
                    if ( $result ) {
                        if ( get_option( 'etsy_api_count' ) < $this->etsy_api_limit ) {

                            if ( isset( $InfoData->primary_color ) || isset( $InfoData->color ) ) {

                                $InfoData->primary_color = isset( $InfoData->primary_color ) ? $InfoData->primary_color : $InfoData->color;
                                if ( $etsyvaluesid = $this->changeColorstoEtsycolorcodes( $InfoData->primary_color ) ) {

                                    $this->updateApiLimitVal( get_option( 'etsy_api_count' ) );
                                    $primarycolorpush = $this->uploadColorTypes( $result, $updatetask, $etsyvaluesid, '200' );
                                    if ( $primarycolorpush === false ) {
                                        $this->additionalMessages = $primarycolorpush;
                                    }
                                }
                            }

                            if ( ! empty( $InfoData->has_variation ) ) {
                                // Uploading Variation
                                $variation_data = $this->get_variation_from_feed( $rawData['item_id'] );
                                if ( is_array( $variation_data ) && count( $variation_data ) > 0 ) {
                                    $variation_data       = $this->getpreparedVariationDataforEtsy( $variation_data, $result, $first_upload );
                                    $varationUploadresult = $this->variationUpload( $variation_data, $result );
                                    if ( $varationUploadresult ) {
                                        $this->resposeData['variation_result'] = $this->variation_upload_message;
                                    } else {
                                        $this->resposeData['variation_result'] = $this->variation_upload_message;
                                    }
                                } else {
                                    $this->resposeData['variation_result'] = 'Child Not Found';
                                }
                            } else {
                                $this->resposeData['variation_result'] = 'Variation not available for this item.';
                            }

                            // Uploading Image for etsy
                            $overwrite = true;
                            if ( $rawData['listing_image_id'] ) {
                                $upload_additional = true;
                                // $imageupload = $rawData['listing_image_id'];
                                $imageupload = $this->submit_listing_images( $result, $rawData['id'], $rawData['item_id'], $InfoData->image_link, $overwrite, $upload_additional );
                            } else {
                                if ( isset( $InfoData->image_link ) ) {
                                    $imageupload = $this->submit_listing_images( $result, $rawData['id'], $rawData['item_id'], $InfoData->image_link, $overwrite, true );
                                } else {
                                    $imageupload = null;
                                }
                            }
                            $this->resposeData['status']     = 'CONTINUE';
                            $this->resposeData['image_id']   = $imageupload;
                            $this->resposeData['updatetask'] = $updatetask;

                        } else {

                            $this->resposeData['status']  = 'HAULT';
                            $this->resposeData['message'] = 'The etsy Api Limit exceeded.';
                            $this->resposeData['time']    = $this->timedifference;

                        }
                    } else {
                        $this->resposeData['status']        = 'SUCCESS';
                        $this->resposeData['failed_reason'] = $this->failedReason;
                        $this->resposeData['time']          = $this->timedifference;
                    }
                } else {

                    $this->resposeData['status']  = 'CONTINUE';
                    $this->resposeData['message'] = $rawData['error'];
                    $this->resposeData['time']    = $this->timedifference;

                }
            } else {

                $this->resposeData['status'] = 'FINISHED';

            }

            return $this->resposeData;
        }

        public function stateResolver( $listing_id, $item_id ) {
            $etsyListingstate = $this->checkStatusInEtsy( $listing_id );
            $defaultState     = etcpf_get_etsy_settings( 'state' );
            if ( $etsyListingstate ) {
                if ( $etsyListingstate === 'removed' ) {
                    $state = $defaultState;
                } elseif ( $etsyListingstate === 'active' ) {
                    if ( ! $this->is_product_in_stock( $item_id ) ) {
                        $state = 'inactive';
                    } else {
                        $state = 'active';
                    }
                } elseif ( $etsyListingstate === 'draft' ) {
                    $state = 'draft';
                } elseif ( $etsyListingstate === 'inactive' ) {
                    $state = 'inactive';
                } elseif ( $etsyListingstate === 'edit' ) {
                    $state = 'inactive';
                }

                return $state;
            }

            return $defaultState;
        }

        public function variationImageManagement( $remotevarLists, $listing_id ) {
            $images     = maybe_unserialize( get_option( 'etcpf_variation_image_linkls' ) );
            $imageArray = array();
            if ( is_array( $images ) && count( $images ) <= 0 ) {
                $this->resposeData['variation_images'] = 'Variation images is not formed properly.';

                return false;
            }
            if ( is_object( $remotevarLists ) && ( $remotevarLists->count > 0 ) ) {
                $property = $remotevarLists->results->price_on_property[0];
                foreach ( $remotevarLists->results->products as $key => $product ) {
                    $imagename = $images[ $product->sku ];
                    foreach ( $product->property_values as $p => $properties ) {
                        if ( $properties->property_id === $property ) {
                            if ( $properties->property_name === 'Primary color' ) {
                                $properties->property_name = 'color';
                            }
                            $remoteImageId = intval( $this->getImageFromImageLink( $imagename ) );
                            if ( $remoteImageId <= 0 ) {
                                continue;
                            }
                            $imageArray[] = array(
                                'property_id' => $properties->property_id,
                                'value_id'    => $properties->value_ids[0],
                                'image_id'    => $remoteImageId,
                            );
                        }
                    }
                }
                $url
                    = "https://openapi.etsy.com/v3/application/shops/$this->shop_id/listings/$listing_id/variation-images";

                try {
                    $response    = wp_remote_post(
                        $url,
                        array(
                            'timeout'     => 120,
                            'redirection' => 5,
                            'method'	=> 'POST',
                            'blocking'    => true,
                            'headers'     => array(
                                'Content-Type'  => 'application/json',
                                'Expect'        => '',
                                'x-api-key'     => get_option( 'etcpf_api_key' ),
                                'Authorization' => 'Bearer '
                                    . get_option( 'etcpf_oauth_token' )
                            ),
                            'body'        => json_encode(
                                array(
                                    'variation_images' => json_encode( $imageArray ),
                                )
                            ),
                        )
                    );
                    $http_status = wp_remote_retrieve_response_code( $response );
                    $response    = wp_remote_retrieve_body( $response );
                    if ( false === $response ) {
                        throw new Exception( 'wp_remote_post_error', $http_status );
                    }
                    if ( 200 != $http_status ) {
                        throw new Exception( $response, $http_status );
                    }
                    if ( json_decode( $response ) ) {
                        $this->resposeData['variation_images'] = $response;

                        return true;
                    }
                } catch ( Exception $e ) {
                    /*
                    trigger_error(sprintf(
                        'Curl failed with error #%d: %s',
                        $e->getCode(), $e->getMessage()),
                        E_USER_ERROR);*/
                    $this->resposeData['variation_images'] = 'Variation image could not be liked, error was ' . $e->getMessage();

                    return false;
                }
            }

            return false;
        }

        public function getImageFromImageLink( $sku ) {
            global $wpdb;
            $table  = $wpdb->prefix . 'etcpf_image_links';
            $result = $wpdb->get_var( "SELECT remote_image_id FROM $table WHERE image_name='{$sku}'" );

            return $wpdb->last_error ? false : $result;
        }

        public function prepareData( $data ) {
            if ( stripos( $data->taxonomy_path, '|' ) ) {
                $current_taxonomy_id = explode( '|', $data->taxonomy_path );
            } else {
                $current_taxonomy_id = explode( ',', $data->taxonomy_path );
            }

            $arraytags = array();
            if ( isset( $data->tags ) ) {
                if ( is_object( $data->tags ) ) {
                    $data->tags = (array) $data->tags;
                } else {
                    $data->tags = explode( ',', $data->tags );
                }
                // removes # if first tags starts with hash #
                foreach ( $data->tags as $key => $tags ) {
                    if ( ( stripos( $tags, '#' ) === 0 ) ) {
                        $data->tags[ $key ] = substr( $tags, 1, strlen( $tags ) );
                    }

                    if ( ( strpos( $tags, '_' ) !== false ) ) {
                        $data->tags[ $key ] = ltrim( str_replace( '_', ' ', $tags ) );
                    }
                }
                $tag_array = array_filter(
                    $data->tags,
                    function ( $arrayEntry ) {
                        return preg_match( '/^[a-z\d\-_\s]+$/i', $arrayEntry );
                    }
                );

                $thtags = array_splice( $tag_array, 0, 12 );
                if ( is_array( $thtags ) && count( $thtags ) > 0 ) {
                    foreach ( $thtags as $arrayEntry ) {
                        if ( strlen( $arrayEntry ) <= 20 ) {
                            $tag_final[] = $arrayEntry;
                        } else {
                            $tag_final[] = substr( $arrayEntry, 0, 19 );
                        }
                    }
                    $data->tags = array_unique( $tag_final );
                } else {
                    $data->tags = null;
                }
            } else {
                $data->tags = '';
            }

            if ( isset( $data->title ) && strlen( $data->title ) > 140 ) {
                $chars = 130;
                preg_match( '/^.{0,' . $chars . '}(?:.*?)\b/iu', $data->title, $title );
                $data->title = $title[0];
            } else {
                $data->title = isset( $data->title ) ? $data->title : 'No Title';
            }
            if ( isset( $data->title ) && substr_count( $data->title, '$' ) > 0 ) {
                $data->title = html_entity_decode( str_replace( '$', 'USD ', $data->title ) );
            }
            $data->title = str_replace( '&amp;', ' and ', $data->title );
            $data->title = str_replace( '&', ' and ', $data->title );

            if ( ( stripos( $data->title, '.' ) === 0 ) || ( stripos( $data->title, '#' ) === 0 ) || ( stripos( $data->title, '"' ) === 0 ) ) {
                $data->title = substr( $data->title, 1, strlen( $data->title ) );
            }
            if ( substr( $data->title, - 1 ) == '"' ) {
                $data->title = substr( $data->title, 0, - 1 );
            }

            $data->title            = html_entity_decode( $data->title );
            $datatobeuploadedOnEtsy = array(
                'title'                => strip_tags( $data->title ),
                'sku'                  => empty( $data->sku ) ? '' : $data->sku,
                'description'          => strip_tags( $data->description ),
                'shipping_template_id' => $this->shipping_template_id,
                /*!empty($this->etsy_calculated_shipping) ? $this->etsy_calculated_shipping : */
                'state'                => $data->state,
                'taxonomy_id'          => intval(end( $current_taxonomy_id )),
                'tags'                 => $data->tags,
                'who_made'             => etcpf_get_etsy_settings( 'who_made_it' ),
                'is_supply'            => intval( etcpf_get_etsy_settings( 'is_supply' ) ),
                'when_made'            => etcpf_get_etsy_settings( 'when_made' ),
                'recipient'            => isset( $data->recipient ) ? $data->recipient : 'not_specified',
                'style'                => array( 'Avant garde' ),
                'language'             => etcpf_get_etsy_settings( 'shop_language' ) ? etcpf_get_etsy_settings( 'shop_language' ) : substr( get_locale(), 0, 2 ),
                /*
                'shop_section_id',
                  'image_ids',
                  'is_customizable',
                  'non_taxable',
                  'image',
                  'processing_min',
                  'processing_max',
                  */
            );
            if ( isset( $data->occasion ) ) {
                $datatobeuploadedOnEtsy['occasion'] = $data->occasion;
            }
            if ( isset( $data->holiday ) ) {
                $datatobeuploadedOnEtsy['holiday'] = $data->holiday;
            }
            if ( isset( $data->materials ) ) {
                $datatobeuploadedOnEtsy['materials'] = $data->materials;
            }

            if ( empty( $data->has_variation ) ) {
                $datatobeuploadedOnEtsy['quantity'] = $data->quantity > 0 ? ( $data->quantity < 999 ? $data->quantity : 999 ) : 0;
                $datatobeuploadedOnEtsy['price']    = ! empty( $data->price ) ? floatval( $data->price ) : 0.00;
            } else {
                if ( ! isset( $data->item_group_id ) && empty( $result['listing_id'] ) ) {
                    $datatobeuploadedOnEtsy['quantity'] = $data->quantity > 0 ? ( $data->quantity < 999 ? $data->quantity : 999 ) : 1;
                    $datatobeuploadedOnEtsy['price']    = $data->price > 0 ? floatval( $data->price ) : 1.00;
                }
            }

            /*
            $data->primary_color = 'Blue';
            $data->secondary_color = 'Red';*/

            return $datatobeuploadedOnEtsy;
        }

        public function returnUploadedStatus( $result ) {
            $variationMessages = json_decode( $result['variation_upload_result'] );
            $responseData      = array(
                'id'               => $result['id'],
                'item_id'          => $result['item_id'],
                'data'             => maybe_unserialize( $result['data'] ),
                'listing_id'       => $result['listing_id'],
                'variation_result' => isset( $variationMessages->results->products ) ? count( $variationMessages->results->products ) . ' child products were uploaded' : 'The product parent product',
                'image_id'         => $result['listing_image_id'],
                'updatetask'       => false,
            );

            return $responseData;
        }

        public function changeColorstoEtsycolorcodes( $colorname ) {
            $colorandcolorcodesArray = array(
                'beige'     => '1213',
                'black'     => '1',
                'blue'      => '2',
                'bronze'    => '1216',
                'brown'     => '3',
                'clear'     => '1219',
                'copper'    => '1218',
                'gold'      => '1214',
                'gray'      => '5',
                'green'     => '4',
                'orange'    => '6',
                'pink'      => '7',
                'purple'    => '8',
                'rainbow'   => '1220',
                'red'       => '9',
                'rose gold' => '1217',
                'silver'    => '1215',
                'white'     => '10',
                'yellow'    => '11',
            );
            if ( array_key_exists( strtolower( $colorname ), $colorandcolorcodesArray ) ) {
                return $colorandcolorcodesArray[ strtolower( $colorname ) ];
            }

            return false;
        }

        public function uploadColorTypes( $listing_id, $type, $data, $attributetypes ) {
            if ( $listing_id ) {
                global $wpdb;
                $table = $wpdb->prefix . 'etcpf_listings';

                $url
                    = "https://openapi.etsy.com/v3/application/shops/$this->shop_id/listings/$listing_id/properties/$attributetypes";

                $response      = wp_remote_post(
                    $url,
                    array(
                        'timeout'     => 60,
                        'redirection' => 5,
                        'blocking'    => true,
                        'headers'     => array(
                            'Content-Type'  => 'application/json',
                            'Expect'        => '',
                            'x-api-key'     => get_option( 'etcpf_api_key' ),
                            'Authorization' => 'Bearer '
                                . get_option( 'etcpf_oauth_token' )
                        ),
                        'body'        => json_encode( array( 'value_ids' => array( $data ) ) ),
                    )
                );
                $response_code = wp_remote_retrieve_response_code( $response );
                $body          = wp_remote_retrieve_body( $response );

                return $response;
            }

            return 'NO_LISTING_ID_FOUND';
        }

        public function update_listing_by_id( $id, $args ) {
            global $wpdb;
            $table    = $wpdb->prefix . 'etcpf_listings';
            $sql      = $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", array( $id ) );
            $data     = $wpdb->get_row( $sql );
            $response = $this->_CURLFORUPLOAD( $data->listing_id, 'PUT', $args, $data->id, true );
            if ( $response == true ) {
                return array(
                    'status'     => 'success',
                    'listing_id' => $data->listing_id,
                    'message'    => 'Item uploaded successfully',
                );
            } else {
                return array(
                    'status'     => 'error',
                    'listing_id' => $data->listing_id,
                    'message'    => $this->failedReason,
                );
            }
        }

        public function checkStatusInEtsy( $listingID ) {
            $url
                = "https://openapi.etsy.com/v3/application/listings/$listingID";


            $response = wp_remote_get(
                $url,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'Expect'        => '',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer '
                            . get_option( 'etcpf_oauth_token' )
                    ),
                )
            );
            if ( wp_remote_retrieve_response_code( $response ) == 200 || wp_remote_retrieve_response_code( $response ) == 201 ) {
                $result = json_decode( wp_remote_retrieve_body( $response ) );

                return $result->state;
            }

            return false;
        }

        public function _CURLFORUPLOAD( $listing_id, $type, $data = array(), $id, $delete_listing_image ) {
            $current_api_set = get_option( 'etsy_api_count' );
            global $wpdb;
            if ( $listing_id == "" ) {
                $listing_id = null;
            }
            $table     = $wpdb->prefix . 'etcpf_listings';
            $xml_state = $data['state'];
            $url
                = "https://openapi.etsy.com/v3/application/shops/$this->shop_id/listings/"
                . $listing_id;
            if ( is_null( $listing_id ) ) {
                $url = rtrim( $url, "/" );
            }
            $this->resposeData['api-url'] = $url;
            $this->resposeData['type']    = $type;
            $acc_req                      = $this->prepareHash( $url, 'POST',
                $type );
            if ( $type && $listing_id && $delete_listing_image
                && etcpf_get_etsy_settings( 'images_sync' ) === 'yes'
            ) {
                $images_left = $this->delete_listing_images( $listing_id );
            }
            $method = is_null( $listing_id ) ? 'POST' : 'PATCH';
            if ( isset( $data['shipping_template_id'] ) ) {
                $data['shipping_profile_id'] = $data['shipping_template_id'];
                unset( $data['shipping_template_id'] );
            }
            $response = wp_remote_request(
                $url,
                array(
                    'method'      => $method,
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'Expect'        => '',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer '
                            . get_option( 'etcpf_oauth_token' )
                    ),
                    'body'        => json_encode( $data ),
                )
            );
            if ( is_wp_error( $response ) ) {
                $error_message      = $response->get_error_message();
                $this->failedReason = $error_message;
                $data               = array(
                    'uploaded'      => 3,
                    /* Tried to list but some error poped up */
                    'item_group_id' => 1,
                    /* This item has been tried to upload in etsy. If error came, need to reupload */
                );
                // $this->failedReason = $error_message;
                $wpdb->update( $table, $data, array( 'id' => $id ) );
            } else {
                if ( $response && ( $response['response']['code'] == 200 || $response['response']['code'] == 201 ) ) {
                    $result = json_decode( $response['body'] );
                    if ( is_object( $result ) ) {

                        $listing          = $result;
                        $this->listing_id = $listing->listing_id;
                        if ( $listing->listing_id ) {
                            $data = array(
                                'listing_id'    => $this->listing_id,
                                'upload_result' => maybe_serialize( $listing ),
                                'uploaded'      => 2,
                                /* Listing uploaded but need to upload images and variation */
                                'item_group_id' => 1,
                                'etsy_status'   => 'existing',
                                'etsy_state'    => $listing->state,
                            );
                            if ( $xml_state != 'draft' ) {

                                $updated_state   = array( 'state' => $xml_state );
                                $update_response = wp_remote_request(
                                    "https://openapi.etsy.com/v3/application/shops/"
                                    . $this->shop_id . "/listings/"
                                    . $this->listing_id,
                                    array(
                                        'method'      => 'PATCH',
                                        'timeout'     => 120,
                                        'redirection' => 5,
                                        'blocking'    => true,
                                        'headers'     => array(
                                            'Content-Type'  => 'application/json',
                                            'Expect'        => '',
                                            'x-api-key'     => get_option( 'etcpf_api_key' ),
                                            'Authorization' => 'Bearer '
                                                . get_option( 'etcpf_oauth_token' )
                                        ),
                                        'body'        => json_encode( $updated_state ),
                                    ) );
                                if ( $update_response
                                    && ( $update_response['response']['code']
                                        == 200
                                        || $update_response['response']['code']
                                        == 201 )
                                ) {
                                    $state_result
                                        = json_decode( $update_response['body'] );
                                    if ( is_object( $state_result ) ) {

                                        $listing            = $state_result;
                                        $this->listing_id
                                            = $listing->listing_id;
                                        $data['etsy_state'] = $listing->state;
                                    }
                                }
                            }


                            if ( $wpdb->update( $table, $data,
                                array( 'id' => $id ) )
                            ) {
                                return $listing->listing_id;
                            }

                            return $listing->listing_id;

                        } else {
                            $data               = array(
                                'upload_result' => maybe_serialize( $response['body'] ),
                                'uploaded'      => 3,
                                /* Tried to list but some error poped up */
                                'item_group_id' => 1,
                                /* This item has been tried to upload in etsy. If error came, need to reupload */
                            );
                            $this->failedReason = $response['body'];
                            if ( $wpdb->update( $table, $data, array( 'id' => $id ) ) ) {
                                return $listing->listing_id;
                            }

                            return false;
                        }
                    }
                    $data = array(
                        'upload_result' => (string) $response['response']['message'],
                        'uploaded'      => 3,
                        /* Tried to list but some error poped up */
                        'item_group_id' => 1,
                        /* This item has been tried to upload in etsy. If error came, need to reupload */
                    );
                    $wpdb->update( $table, $data, array( 'id' => $id ) );
                    $this->failedReason = $response;

                    return false;
                }
                if ( $response['body'] ) {
                    if ( stripos( $response['body'], 'removed' ) !== false ) {
                        $error_message = "This product is not found in your Etsy shop. Want to re-upload it ? <a class='relist-to-etsy' data-id=" . $id . " href='#_'> Yes </a><br>  <a class='delete-from-etsy' data-id=" . $id . " onclick='etcpf_delete_listing(" . $id . ",this);' href='#_'>Delete</a>";
                    } elseif ( stripos( $response['body'], 'Shipping template does not exist' ) !== false ||
                        stripos( $response['body'], 'Shipping profile name does not match the shipping profile type' ) !== false ||
                        stripos( $response['body'], "Expected int value for 'shipping_template_id'" ) !== false
                    ) {
                        $error_message = 'Shipping template does not exist. <a href="admin.php?page=etsy-export-feed-shipping&fetch=true">Click here</a> to fetch/create shipping templates.';
                    } else {
                        $error_message = $response['response']['message'] . ': ' . $response['body'];
                    }
                } else {
                    $error_message = 'SOMETHING WENT WRONG WITH ETSY. NOTHING WAS GIVEN BACK AS RESPONSE. TRY AGAIN. THANKS';
                }
                $data = array(
                    'upload_result' => $error_message,
                    'uploaded'      => 3, /* Tried to list but some error poped up */
                );
                if ( $response['body'] == "Can't move a listing from state removed to state draft" ) {
                    $data['etsy_status'] = 'removed';
                }
                $this->failedReason = $error_message;
                $wpdb->update( $table, $data, array( 'id' => $id ) );
            }

            return false;
        }

        public function updateApiLimitVal( $current_api_set ) {
            $newvalue = intval( $current_api_set ) + 1;
            update_option( 'etsy_api_count', $newvalue );

            return true;
        }

        public function get_variation_from_feed( $parentId ) {
            global $wpdb;
            $table      = $wpdb->prefix . 'etcpf_listing_variations';
            $qry        = $wpdb->prepare( "SELECT * FROM {$table} WHERE parent_id=%d", array( $parentId ) );
            $variations = $wpdb->get_results( $qry );
            if ( is_array( $variations ) ) {
                return $variations;
            }

            return null;
        }

        public function get_variation( $idProduct ) {
            if ( function_exists( 'get_product' ) ) {
                $product = wc_get_product( $idProduct );
                if ( $product->is_type( 'variable' ) ) {

                    $product = new WC_Product_Variable( $idProduct );

                    $available_variations = $product->get_available_variations(); // get all child variations

                    /*$variation_variations = $product->get_variation_attributes();*/// get all attributes by variations

                    $result = array( $available_variations ); // only to see the result you can use var_dump, error_log, etc.

                    $variation_data     = array();
                    $variation_price    = array();
                    $variation_price    = array();
                    $variation_quantity = array();
                    $variation_sku      = array();
                    $i                  = 0;
                    foreach ( $result[0] as $key => $value ) {

                        $variation_data[ $i ] = $value['attributes'];
                        if ( $value['display_price'] ) {
                            $variation_price[ $i ] = $value['display_price'];
                        }

                        $variation_sku[ $i ]      = $value['sku'];
                        $variation_quantity[ $i ] = $value['max_qty'];
                        $i ++;
                    }
                    $varresult = array(
                        'variation_data'  => $variation_data,
                        'variation_price' => $variation_price,
                        'variation_sku'   => $variation_sku,
                        'quantity'        => $variation_quantity,
                    );

                    return $varresult;

                } else {
                    return false;
                }
            }
        }

        public function get_images( $productId, $feature_image ) {
            if ( function_exists( 'WC_product' ) ) {
                $product = new WC_product( $productId );
            } elseif ( function_exists( 'wc_get_product' ) ) {
                $product = wc_get_product( $productId );
            } else {
                return null;
            }

            if ( gettype( $product ) == 'boolean' ) {
                return null;
            }

            $attachmentIds = $product->get_gallery_image_ids();
            $imgUrls       = array();
            foreach ( $attachmentIds as $attachmentId ) {
                $imgUrls[] = wp_get_attachment_url( $attachmentId );
            }

            $this->resposeData['product_type'] = $product->is_type( 'variable' );
            if ( $product->is_type( 'variable' ) ) {
                $variationImages = $this->getVariationImages( $product->get_available_variations(), $feature_image );
            } else {
                $variationImages = false;
            }

            if ( $imgUrls && $variationImages ) {
                return array_merge( $variationImages, $imgUrls );
            } elseif ( $imgUrls ) {
                return $imgUrls;
            } elseif ( $variationImages ) {
                return $variationImages;
            } else {
                return false;
            }

        }

        public function getVariationImages( $variations, $feature_image ) {
            $images = array();
            foreach ( $variations as $variation ) {
                if ( $feature_image === $variation['image']['full_src'] ) {
                    continue;
                }
                $images[] = $variation['image']['full_src'];
            }

            return $images;
        }

        public function submit_listing_images( $listing_id, $db_listing_id, $item_id, $feature_image, $overwrite, $upload_additional = false ) {
            $returnvalue = false;

            if ( $upload_additional == true ) {
                $additional_images = $this->get_images( $item_id, $feature_image );
                if ( is_array( $additional_images ) ) {
                    $additional_images = array_reverse( $additional_images );
                }

                $this->resposeData['additional_images'] = $additional_images;
                if ( $additional_images ) {
                    $additional_image_id = array();
                    if ( count( $additional_images ) >= 9 ) {
                        $count = 9;
                    } else {
                        $count = count( $additional_images );
                    }
                    for ( $t = 0; $t < $count; $t ++ ) {
                        $rank     = $t + 2;
                        $filename = 'temp' . $this->generateRandomString( 10 ) . '.jpg';
                        $result   = $this->ManageImageForUplaod( $item_id, $additional_images[ $t ], $overwrite, $listing_id, $filename, $rank );
                        if ( $result ) {
                            $additional_image_id[ $t ] = $result;
                        }
                    }
                    if ( $additional_image_id ) {
                        $data  = array( 'additional_image_id' => implode( ',', $additional_image_id ) );
                        $trans = $this->logUploadResult( $data, $listing_id );
                    } else {
                        $data  = array( 'additional_image_id' => null );
                        $trans = $this->logUploadResult( $data, $listing_id );
                    }
                }
            }

            if ( $feature_image ) {
                $filename = 'temp.jpg';
                $result   = $this->ManageImageForUplaod( $item_id, $feature_image, $overwrite, $listing_id, $filename, $rank = 1 );
                if ( $result ) {
                    $data        = array(
                        'listing_image_id' => $result,
                        // 'uploaded' => 5 /* Listing and image for that listing uploaded, now need to upload variation */
                    );
                    $trans       = $this->logUploadResult( $data, $listing_id );
                    $returnvalue = $result;
                } else {
                    $data  = array(
                        'listing_image_id' => null,
                    );
                    $trans = $this->logUploadResult( $data, $listing_id );
                    if ( $trans ) {
                        $returnvalue = $result;
                    } else {
                        $returnvalue = false;
                    }
                }
            }

            return $returnvalue;
        }

        public function ManageImageForUplaod( $item_id, $image, $overwrite, $listing_id, $filename, $rank ) {
            if ( $image && stripos( $image, 'http' ) !== false ) {
                global $wpdb;
                $tbl    = $wpdb->prefix . 'etcpf_listings';
                $wp_dir = wp_upload_dir( 'basedir' );
                $dir    = $wp_dir['basedir'];
                if ( ! file_exists( $dir . '/fetchedfiles' ) ) {
                    if ( is_writable( $dir ) ) {
                        mkdir( $dir . '/fetchedfiles' );
                    } else {
                        echo '<pre>';
                        print_r( 'Directory ' . $dir
                            . ' is not writable. Please update the file permission' );
                        echo '</pre>';
                        exit();
                    }
                }
                $curlfiledDir = $dir . '/fetchedfiles/';
                $wp_dir = wp_upload_dir();
                $checkImageHost = parse_url( $image );
                $serverHost
                    = sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) );
                if ( isset( $checkImageHost['host'] )
                    && preg_match_all( '#[-a-zA-Z0-9@:%_\+.~\#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#si',
                        $checkImageHost['host'], $serverHost )
                ) {
                    $imagePath = str_replace( $wp_dir['baseurl'],
                        $wp_dir['basedir'], $image );
                    $imageName = str_replace( $wp_dir['baseurl'], '', $image );
                    $array     = explode( '/', $imageName );
                    $imageName = end( $array );
                    $realpath  = realpath( $imagePath );
                    if ( ! file_exists( $realpath ) ) {
                        // checks if the image is available to view
                        if ( ! $this->check_wp_image_url_exists( $image ) ) {
                            $this->failedReason = "Image Doesn't Exists";

                            return null;
                        }
                        // Hosting providers like WPEngine supports viewing image directly from S3 buckets
                        // so save those images inside /fetchedfiles
                        $operation = $this->save_remote_image_if_exists( $image, $curlfiledDir, $filename );
                        if ( $operation ) {
                            $imagePath = realpath( $curlfiledDir . $filename );
                            $imageName = $filename;
                        }
                    }

                } else {
                    $operation = $this->save_remote_image_if_exists( $image, $curlfiledDir, $filename );
                    if ( ! $operation ) {
                        $this->failedReason = 'IMAGE IS LOADED FROM CDN AND COULD NOT BE FETCHED';

                        return null;
                    }
                    $imagePath = realpath( $curlfiledDir . $filename );
                    $imageName = $filename;
                }
                if ( $imageName ) {
                    $result = $this->_CURLIMAGE_TO_ETSY( $listing_id, $imagePath, $overwrite, $rank );
                    $this->defineImageAndIdLink( $result, $imageName, $item_id );
                    if ( $result ) {
                        $get_current_listing_images = $this->get_listing_images_from_etsy( $listing_id );
                        $current_listing_images = $get_current_listing_images->results;
                        foreach ( $current_listing_images as $listing_image ) {
                            $listing_image_id = $listing_image->listing_image_id;
                            $image_left = $this->images_left['listing_image_id'];
                            if ( $listing_image_id == $image_left ) {
                                $delete_listing_image_url = "https://openapi.etsy.com/v3/application/shops/{$this->shop_id}/listings/{$listing_id}/images/{$listing_image_id}";

                                $test = wp_remote_request(
                                    $delete_listing_image_url,
                                    array(
                                        'method'  => 'DELETE',
                                        'headers' => array(
                                            'Content-Type'  => 'application/json',
                                            'Expect'        => '',
                                            'x-api-key'     => get_option( 'etcpf_api_key' ),
                                            'Authorization' => 'Bearer '
                                                . get_option( 'etcpf_oauth_token' )
                                        )
                                    )
                                );
                            }
                        }

                        return $result;
                    }

                    return null;
                }

                return null;
            }
            return 'empty image';
        }

        public function get_listing_images_from_etsy($listing_id) {
            $url
                = "https://openapi.etsy.com/v3/application/listings/$listing_id/images";

            $listing_images        = wp_remote_get(
                $url,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'Expect'        => '',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer '
                            . get_option( 'etcpf_oauth_token' )
                    ),
                )
            );
            if ( ! is_wp_error( $listing_images ) ) {
                $listing_images = json_decode( wp_remote_retrieve_body( $listing_images ) );
                return $listing_images;
            }
        }

        function check_wp_image_url_exists( $url ) {
            $ch = curl_init( $url );
            curl_setopt( $ch, CURLOPT_NOBODY, true );
            curl_exec( $ch );
            $response = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            curl_close( $ch );

            if ( $response != 200 ) {
                return false;
            }

            return true;
        }

        public function save_remote_image_if_exists( $image, $curlfiledDir, $filename ) {
            $response      = wp_remote_get( $image );
            $response_code = wp_remote_retrieve_response_code( $response );
            $body          = wp_remote_retrieve_body( $response );
            if ( $response_code != 200 ) {
                return false;
            }
            $fp = fopen( $curlfiledDir . $filename, 'wb' );
            fwrite( $fp, $body );
            fclose( $fp );

            return true;
        }

        public function delete_listing_images( $listing_id ) {

            $url
                = "https://openapi.etsy.com/v3/application/listings/$listing_id/images";

            $listing_images        = wp_remote_get(
                $url,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'Expect'        => '',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer '
                            . get_option( 'etcpf_oauth_token' )
                    ),
                )
            );
            if ( ! is_wp_error( $listing_images ) ) {
                $listing_images
                    = json_decode( wp_remote_retrieve_body( $listing_images ),
                    1 );
                if ( is_array( $listing_images )
                    && $listing_images['count'] > 1
                ) {
                    $images_left = array_pop($listing_images['results']);
                    $this->images_left = $images_left;

                    foreach ( $listing_images['results'] as $listing_image ) {
                        $listing_image_id = $listing_image['listing_image_id'];
                        $delete_listing_image_url
                            = "https://openapi.etsy.com/v3/application/shops/$this->shop_id/listings/$listing_id/images/$listing_image_id ";

                        $test = json_decode( wp_remote_request(
                            $delete_listing_image_url,
                            array(
                                'method'  => 'DELETE',
                                'headers' => array(
                                    'Content-Type'  => 'application/json',
                                    'Expect'        => '',
                                    'x-api-key'     => get_option( 'etcpf_api_key' ),
                                    'Authorization' => 'Bearer '
                                        . get_option( 'etcpf_oauth_token' )
                                )
                            )
                        ));
                    }
                } else {
                    $images_array = $listing_images['results'];
                    $images_left = $images_array[0];
                    $this->images_left = $images_left;
                }
            }
            return $images_left;
        }

        public function _CURLIMAGE_TO_ETSY( $listing_id, $image_file, $overwrite, $rank ) {
            $this->updateApiLimitVal( get_option( 'etsy_api_count' ) );
            $url
                = "https://openapi.etsy.com/v3/application/shops/$this->shop_id/listings/$listing_id/images";
            $url_to_send = $this->prepareHash( $url, 'POST' );
            $boundary    = wp_generate_password( 24 );
            $headers     = array(
                'content-type'  => 'multipart/form-data; boundary=' . $boundary,
                'x-api-key'     => get_option( 'etcpf_api_key' ),
                'Authorization' => 'Bearer ' . get_option( 'etcpf_oauth_token' )
            );
            $payload     = '';
            $payload     .= '--' . $boundary;
            $payload     .= "\r\n";
            $payload     .= 'Content-Disposition: form-data; name="image"; filename="'
                . basename( $image_file ) . '"' . "\r\n";
            $payload     .= "\r\n";
            $payload     .= file_get_contents( $image_file );
            $payload     .= "\r\n";
            $payload     .= '--' . $boundary . '--';

            $response = wp_remote_post(
                $url_to_send,
                array(
                    'headers' => $headers,
                    'body'    => $payload,
                )
            );

            $result = ( ! is_wp_error( $response ) )
                ? json_decode( wp_remote_retrieve_body( $response ) )
                : null;
            if ( $result ) {

                $listing_image_id = $result->listing_image_id;

                return $listing_image_id;
            }

            return false;
        }

        public function defineImageAndIdLink( $id, $imageName, $item_id ) {
            if ( $id ) {
                global $wpdb;
                $table = $wpdb->prefix . 'etcpf_image_links';
                $getid = $wpdb->get_var( "SELECT id FROM $table WHERE image_name='{$imageName}'" );
                if ( $getid ) {
                    $wpdb->update(
                        $table,
                        array(
                            'remote_image_id' => $id,
                            'image_name'      => $imageName,
                        ),
                        array( 'id' => $getid )
                    );
                } else {
                    $wpdb->insert(
                        $table,
                        array(
                            'parent_product_id' => $item_id,
                            'remote_image_id'   => $id,
                            'image_name'        => $imageName,
                        )
                    );
                }

                return $wpdb->last_error ? false : true;
            }

            return false;
        }

        public function logUploadResult( $data, $listing_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'etcpf_listings';
            if ( $wpdb->update( $table, $data, array( 'listing_id' => $listing_id ) ) ) {
                return true;
            }

            return false;
        }

        public function set_shipping_template_id( $shipping_template_id ) {
            $this->shipping_template_id = $shipping_template_id;
        }

        public function get_settings() {
            global $wpdb;
            $table    = $wpdb->prefix . 'etcpf_etsy_configuration';
            $settings = $wpdb->get_results( "SELECT * FROM $table" );
            foreach ( $settings as $setting ) {
                $title        = $setting->configuration_title;
                $this->$title = $setting->configuration_value;
                // $this->etsy_api_limit = $setting->etsy_api_limit;
            }

            $feed_table                         = $wpdb->prefix . 'etcpf_feeds';
            $shipping_template_id_column_exists = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$feed_table' AND column_name = 'SHIPPING_TEMPLATE_ID' " );

            if ( isset( $_REQUEST['id'] ) && ! empty( $shipping_template_id_column_exists ) ) {
                $feed_id                    = $_REQUEST['id'];
                $feed_shipping_template_id  = $wpdb->get_results( "SELECT SHIPPING_TEMPLATE_ID FROM $feed_table WHERE id = '$feed_id'" );
                $this->shipping_template_id = empty( $feed_shipping_template_id ) ? get_option( 'etcpf_shipping_template_id' ) : $feed_shipping_template_id[0]->SHIPPING_TEMPLATE_ID;
            } else {
                $this->shipping_template_id = get_option( 'etcpf_shipping_template_id' );
            }

            $this->shop_id = get_option( 'etcpf_shop_id' );
        }

        public function get_submitted_listting( $itemid ) {
            global $wpdb;
            $table   = $wpdb->prefix . 'etcpf_listings';
            $sql     = $wpdb->prepare( "SELECT * FROM $table WHERE uploaded > %d AND feed_id = %d", array(
                1,
                $itemid
            ) );
            $listing = $wpdb->get_results( $sql );
            if ( count( $listing ) == 0 ) {
                return false;
            }

            return $listing;
        }

        public function get_remaining_listting( $itemid, $task ) {
            global $wpdb;
            $table   = $wpdb->prefix . 'etcpf_listings';
            $sql     = $wpdb->prepare( "SELECT * FROM $table WHERE ( uploaded = %d ) AND feed_id = %d", array(
                intval( $task ),
                $itemid
            ) );
            $listing = $wpdb->get_results( $sql );
            if ( count( $listing ) == 0 ) {
                return false;
            }

            return true;
        }

        public function checkuploadingListing( $itemid ) {
            global $wpdb;
            $table   = $wpdb->prefix . 'etcpf_listings';
            $sql     = $wpdb->prepare( "SELECT * FROM $table WHERE (uploaded != %d AND uploaded!=%d) AND feed_id = %d", array(
                2,
                3,
                $itemid
            ) );
            $listing = $wpdb->get_results( $sql );
            if ( count( $listing ) == 0 ) {
                return false;
            }

            return $listing;
        }

        public function getListingById( $id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'etcpf_listings';
            $data  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table where id=%d", array( $id ) ), ARRAY_A );
            if ( $data ) {
                return $data;
            }

            return false;
        }

        public function getListingWithflagNull( $itemid ) {
            global $wpdb;
            $table   = $wpdb->prefix . 'etcpf_listings';
            $sql     = $wpdb->prepare( "SELECT * FROM $table WHERE ( item_group_id IS NULL) AND feed_id = %d", array( $itemid ) );
            $listing = $wpdb->get_results( $sql );
            if ( count( $listing ) == 0 ) {
                return true;
            }

            return false;
        }

        public function checkifListingNeedstobeuploaded( $itemid, $listing_id, $task ) {
            $data                      = array();
            $data['status_with_zero']  = false;
            $data['status_with_four']  = false;
            $data['status_with_five']  = false;
            $data['status_with_one']   = false;
            $data['status_with_three'] = false;
            global $wpdb;
            $table   = $wpdb->prefix . 'etcpf_listings';
            $sql     = $wpdb->prepare( "SELECT * FROM $table WHERE ( uploaded = %d ) AND feed_id = %d", array(
                intval( $task ),
                $itemid
            ) );
            $listing = $wpdb->get_results( $sql );
            if ( count( $listing ) == 0 ) {
                $sql    = $wpdb->prepare( "SELECT * FROM $table WHERE ( uploaded = %d ) AND feed_id = %d LIMIT 1", array(
                    4,
                    $itemid
                ) );
                $result = $wpdb->get_row( $sql );
                if ( $result ) {
                    $data['status_with_four'] = true; /* Uploading status */
                    if ( $result->listing_id ) {
                        $wpdb->update(
                            $table,
                            array(
                                'uploaded'      => 2,
                                'item_group_id' => null,
                            ),
                            array( 'id' => $result->id )
                        );
                    } else {
                        $wpdb->update(
                            $table,
                            array(
                                'uploaded'      => 0,
                                'item_group_id' => null,
                            ),
                            array( 'id' => $result->id )
                        );
                    }
                } else {
                    $data['status_with_four'] = false;
                }
                $data['status_with_three'] = false; /* Uploaded with unsuccessful result */
            } else {
                $data[ 'status_with_' . $task ] = true;
            }

            return $data;
        }

        public function checkFailedListing( $itemid, $faileduploadstatus ) {
            global $wpdb;
            $table   = $wpdb->prefix . 'etcpf_listings';
            $sql     = $wpdb->prepare( "SELECT * FROM $table WHERE ( uploaded = %d ) AND feed_id = %d", array(
                intval( $faileduploadstatus ),
                $itemid
            ) );
            $listing = $wpdb->get_results( $sql );
            if ( count( $listing ) > 0 ) {
                return $listing;
            }

            return false;
        }

        public function get_submitted_listing_by_id( $id ) {
            global $wpdb;
            $table   = $wpdb->prefix . 'etcpf_listings';
            $sql     = $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", array( $id ) );
            $listing = $wpdb->get_row( $sql );
            if ( count( $listing ) == 0 ) {
                return false;
            }

            return $listing;
        }

        /*
        ==============================================================================================================
                    For now not needed.Can use later when etsy supports more than two variations attributes.
                    ===============================================================================================================

                    public function manageVariation($data = array())
                    {
                    if ($this->globalQuantity == null) {
                    $this->globalQuantity = $this->parent_quantity;
                    }

                    $datatobesent = array();
                    $i = 0;
                    foreach ($data as $key => $value) {
                    $datatobesent[$i] = array_values($value);
                    $i++;
                    }
                    $productcombination = $this->combinations($datatobesent);

                    if (count($data) > 0) {
                    $k = 0;
                    $products = array();
                    if (count($data) == 1) {
                    foreach ($data as $key => $value) {
                    foreach ($value as $key => $val) {
                    $products[] = array(
                    'property_values' => $productcombination[$k],
                    'sku' => isset($val['sku']) ? $val['sku'] : $this->sku,
                    'offerings' => array(
                    array(
                    'price' => isset($val['price']) ? $val['price'] : $this->globalPrice,
                    'quantity' => isset($val['quantity']) ? $val['quantity'] : $this->globalQuantity,
                    'is_enabled' => 1,
                    ),
                    ),
                    );
                    $k++;
                    }
                    }
                    } else {
                    foreach ($data as $key => $value) {
                    foreach ($value as $key => $val) {
                    $products[] = array(
                    'property_values' => $productcombination[$k],
                    'sku' => isset($this->sku) ? $this->sku : $val['sku'],
                    'offerings' => array(
                    array(
                    'price' => isset($this->globalPrice) ? $this->globalPrice : $val['price'],
                    'quantity' => $this->globalQuantity,
                    'is_enabled' => 1,
                    ),
                    ),
                    );
                    $k++;
                    }
                    }
                    }

                    }
                    return $products;
                    }

                    public function combinations($arrays = array())
                    {
                    $result = array(array());
                    foreach ($arrays as $property => $property_values) {
                    foreach ($property_values as $k => $q) {
                    unset($property_values[$k]['quantity']);
                    unset($property_values[$k]['price']);
                    unset($property_values[$k]['sku']);
                    }
                    $tmp = array();
                    foreach ($result as $result_item) {
                    foreach ($property_values as $property_value) {
                    $array1 = array($property => $property_value);
                    unset($array1[0]['quantity']);
                    unset($array1[0]['price']);
                    unset($array1[0]['sku']);
                    $tmp[] = array_merge($result_item, $array1);

                    }
                    }
                    $result = $tmp;
                    }
                    return $result;
                    }
        */

        public function checkPropertyOfEtsy( $attributeName, $custom_attribute_is_used = false ) {
            $attributeName = strtolower( $attributeName );
            if ( preg_match( '/size/', $attributeName )
                // || preg_match( '/tamanho/', $attributeName )
                // || preg_match ( "/größe/", strtolower ( $attributeName ) )
            ) {
                return 100;
            } elseif ( preg_match( '/color/', strtolower( $attributeName ) )
                || preg_match( '/metal color/', strtolower( $attributeName ) )
                // || preg_match ( "/farbe/", strtolower ( $attributeName ) )
                // || preg_match ( "/fárbe/", strtolower ( $attributeName ) )
                // || preg_match (  '/cor/', $attributeName )
            ) {
                return 200;
            } elseif ( preg_match( '/flavor/', $attributeName ) ) {
                return 503;
            } elseif ( preg_match( '/height/', $attributeName ) ) {
                return 505;
            } elseif ( preg_match( '/length/', $attributeName ) ) {
                return 506;
            } elseif ( preg_match( '/material/', $attributeName )
                || preg_match( '/metal/', $attributeName )
                // || preg_match( '/engraving/', $attributeName )
                // || preg_match("/coating/", strtolower ( $attributeName ) )
            ) {
                return 507;
            } elseif ( preg_match( '/width/', strtolower( $attributeName ) ) ) {
                return 512;
            } elseif ( preg_match( '/width scale/', strtolower( $attributeName ) ) ) {
                return 306;
            } elseif ( preg_match( '/finish/', strtolower( $attributeName ) ) ) {
                return 500;
            } elseif ( preg_match( '/diameter/', strtolower( $attributeName ) ) ) {
                return 504;
            } elseif ( preg_match( '/dimensions/', strtolower( $attributeName ) )
                || preg_match( '/dimension/', strtolower( $attributeName ) )
            ) {
                return 501;
            } elseif ( preg_match( '/pattern/', strtolower( $attributeName ) ) ) {
                return 508;
            } elseif ( preg_match( '/fabric/', strtolower( $attributeName ) ) ) {
                return 502;
            } elseif ( preg_match( '/scent/', strtolower( $attributeName ) ) ) {
                return 509;
            } elseif ( preg_match( '/style/', strtolower( $attributeName ) ) ) {
                return 510;
            } elseif ( preg_match( '/weight/', strtolower( $attributeName ) ) ) {
                return 511;
            }
            if ( $custom_attribute_is_used ) {
                return 514;
            }

            return 513;
        }

        public function getVariationDataforEtsy( $variation_data = array(), $listing_id ) {
            $properties               = array();
            $product                  = array();
            $totalVariationAttributes = count( $variation_data['variation_data'][0] );
            if ( intval( $totalVariationAttributes ) <= 2 ) {
                if ( is_array( $variation_data['variation_data'] ) ) {
                    foreach ( $variation_data['variation_data'] as $key => $value ) {
                        if ( is_array( $value ) ) {
                            foreach ( $value as $k => $val ) {
                                $price                         = null;
                                $price                         = $variation_data['variation_price'][ $key ] ? $variation_data['variation_price'][ $key ] : '';
                                $properties[ $k . ':' . $val ] = $price . ':' . $variation_data['variation_sku'][ $key ] . ':' . $variation_data['quantity'][ $key ];
                            }
                        }
                    }

                    $onpropertyValue = key( $variation_data['variation_data'][0] );
                    foreach ( $variation_data['variation_data'] as $key => $value ) {
                        if ( is_array( $value ) ) {
                            $tempproductdata          = array();
                            $temp                     = array();
                            $custom_attribute_is_used = false;
                            foreach ( $value as $k => $val ) {
                                /*$properties[$k.':'.$val] = $variation_data['variation_price'][$key].':'.$variation_data['variation_sku'][$key].':'.$variation_data['quantity'][$key];*/
                                $property_id = $this->checkPropertyOfEtsy( $k, $custom_attribute_is_used );
                                if ( $property_id === 513 ) {
                                    $custom_attribute_is_used = true;
                                }
                                $property_name = str_replace( 'attribute_pa_', '', $k );
                                $regexp        = '/[0-9]-[0-9]/';
                                if ( preg_match( $regexp, $property_name ) ) {
                                    $property_name = preg_replace( '/-/', '.', $property_name );
                                }
                                $property_value = $val;
                                $temp[]         = array(
                                    'property_id'   => $property_id,
                                    'property_name' => $property_name,
                                    'values'        => $property_value,
                                );
                                if ( $k == $onpropertyValue ) {
                                    $offerings = $onpropertyValue . ':' . $val;
                                }
                            }
                            $explodedValue                      = explode( ':', $properties[ $offerings ] );
                            $tempproductdata['property_values'] = $temp;
                            $tempproductdata['sku']             = $explodedValue[1];
                            $tempproductdata['offerings']       = array(
                                array(
                                    'price'      => $explodedValue[0],
                                    'quantity'   => ( $explodedValue[2] < 999 ) ? ( $explodedValue[2] > 0 ? $explodedValue[2] : 10 ) : 999,
                                    'is_enabled' => true,
                                ),
                            );
                            $product[]                          = $tempproductdata;
                        }
                    }
                    $onpropertyId = $this->checkPropertyOfEtsy( $onpropertyValue );
                    $data         = array(
                        'data' => array(
                            'products'             => json_encode( $product ),
                            'price_on_property'    => $onpropertyId,
                            'quantity_on_property' => $onpropertyId,
                            'sku_on_property'      => $onpropertyId,
                        ),
                    );

                    return $data;
                } else {
                    $data         = array();
                    $data['data'] = 'Expected array value for variation data. Got ' . gettype( $variation_data['variation_data'] ) . ' value.';

                    return $data;
                }
            } else {
                $data         = array();
                $data['data'] = 'MORE_THAN_TWO_VARIATION_ATTRIBUTES';

                return $data;
            }

            return null;
        } /*End of function*/

        public function getpreparedVariationDataforEtsy( $data, $listing_id, $first_upload = false ) {
            $propertyidsarray         = array();
            $images                   = array();
            $overallVariationQuantity = 0;
            $product                  = array();
            $properties               = array();
            $count                    = 0;
            $offeringPrices           = null;
            if ( etcpf_get_etsy_settings( 'price_sync' ) == 'no' && ! $first_upload ) {
                $offeringPrices = $this->get_offering_prices_from_etsy( $listing_id );
            }
            if ( is_array( $data ) ) {
                foreach ( $data as $key => $variation_data ) {
                    $temp        = array();
                    $productData = maybe_unserialize( $variation_data->data );

                    /**
                     * Forming images inaccordance with variation attribute and product sku
                     * This is later used in variation image upload
                     */
                    $imgArray                                                                             = explode( '/', $productData->image_link );
                    $imgname                                                                              = end( $imgArray );
                    $images[ isset( $productData->sku ) ? $productData->sku : 'sku-' . $productData->id ] = $imgname;

                    if ( isset( $productData->variation_attributes ) ) {
                        $attributes      = json_decode( $productData->variation_attributes, true );
                        $attributes      = (array) $attributes;
                        $onpropertyValue = $this->checkOnProperty( $attributes );
                        $count           = count( $attributes );
                        if ( is_array( $attributes ) && $count <= 2 ) {

                            if ( count( $attributes ) <= 0 ) {
                                continue;
                            }
                            $custom_attribute_is_used = false;
                            foreach ( $attributes as $k => $value ) {

                                $overallVariationQuantity += $productData->quantity;
                                if ( empty( $properties[ $k . ':' . $value ] ) ) {
                                    if ( is_object( $productData->sku ) ) {
                                        $sku = (array) $productData->sku;
                                        if ( empty( $sku ) ) {
                                            $productData->sku = '';
                                        } else {
                                            $productData->sku = end( $sku );
                                        }
                                    }
                                    $properties[ $k . ':' . $value ] = $productData->price . ':' . $productData->sku . ':' . $productData->quantity;
                                }
                                $property_id = $this->checkPropertyOfEtsy( $k, $custom_attribute_is_used );
                                if ( $property_id === 513 ) {
                                    $custom_attribute_is_used = true;
                                }
                                $propertyidsarray[] = $property_id;
                                $property_name      = $k;
                                $regexp             = '/[0-9]-[0-9]/';
                                if ( preg_match( $regexp, $property_name ) ) {
                                    $property_name = preg_replace( '/-/', '.', $property_name );
                                }

                                if ( preg_match( $regexp, $value ) ) {
                                    $property_value = preg_replace( '/-/', '.', $value );
                                } else {
                                    $property_value = $value;
                                }
                                if ( empty( $property_value ) ) {
                                    $this->resposeData['empty_property_value_products'] = $productData->sku;
                                    continue;
                                }
                                /* $property_value = $value; */
                                $temp[] = array(
                                    'property_id'   => $property_id,
                                    'property_name' => $property_name,
                                    'values'        => array($property_value),
                                );
                                if ( $k == $onpropertyValue ) {
                                    $offerings = $onpropertyValue . ':' . $value;
                                }
                            }
                            if ( count( $temp ) > 0 ) {
                                $tempproductdata['property_values'] = $temp;
                                if ( isset( $properties[ $offerings ] ) ) {
                                    $everythingNeeded       = explode( ':', $properties[ $offerings ] );
                                    $tempproductdata['sku'] = $productData->sku;
                                    if ( isset( $productData->use_sale_price ) ) {
                                        if ( $productData->use_sale_price === true ) {
                                            $offeringPrice = isset( $productData->sale_price ) ? $productData->sale_price : ( isset( $productData->regular_price ) ? $productData->regular_price : $productData->price );
                                        } else {
                                            $offeringPrice = isset( $productData->regular_price ) ? $productData->regular_price : $productData->price;
                                        }
                                    } else {
                                        $offeringPrice = isset( $productData->regular_price ) ? $productData->regular_price : $productData->price;
                                    }
                                    $tempproductdata['offerings'] = array(
                                        array(
                                            'price'      => $offeringPrice,
                                            'quantity'   => isset( $productData->quantity ) ? ( ( $productData->quantity <= 999 ) ? $productData->quantity : 999 ) : 0,
                                            'is_enabled' => true,
                                        ),
                                    );
                                } else {
                                    $tempproductdata['offerings'] = array(
                                        array(
                                            'price'      => $productData->price,
                                            'quantity'   => $productData->quantity ? $productData->quantity : 10,
                                            'is_enabled' => true,
                                        ),
                                    );
                                }
                                // overwrite the variation proce with price from Etsy if price_sync is off.
                                if ( etcpf_get_etsy_settings( 'price_sync' ) == 'no' && count( $offeringPrices ) > 0 && ! $first_upload ) {
                                    $price_array_key = '';
                                    foreach ( $tempproductdata['property_values'] as $key => $value ) {
                                        if ( $price_array_key !== '' ) {
                                            $price_array_key .= '_-_';
                                        }
                                        $price_array_key .= $value['values'][0];
                                    }
                                    if ( isset( $offeringPrices[ $price_array_key ] ) ) {
                                        $tempproductdata['offerings'][0]['price'] = $offeringPrices[ $price_array_key ];
                                    }
                                }

                                $product[] = $tempproductdata;
                            }
                        } else {
                            return 'MORE_THAN_TWO_VARIATION_ATTRIBUTES';
                        }
                    } else {
                        continue;
                        // return false;
                    }
                }

                update_option( 'etcpf_variation_image_linkls', maybe_serialize( $images ) );

                $onpropertyId = $this->checkPropertyOfEtsy( $onpropertyValue );
                $data         = array(
                    'products'             => $product,
                    'price_on_property'    => array_unique( $propertyidsarray ),
                    'quantity_on_property' => array_unique( $propertyidsarray ),
                    'sku_on_property'      => array_unique( $propertyidsarray ),
                );
                return $data;
            }

            return false;
        }

        public function checkOnProperty( $attributes ) {
            if ( $onpropertyvalue = get_option( 'etsy_variation_on_property_' . $this->feedID ) ) {
                if ( array_key_exists( $onpropertyvalue, $attributes ) ) {
                    return $onpropertyvalue;
                } else {
                    return key( array_reverse( $attributes ) );
                }
            } else {
                return key( $attributes );
            }
        }

        public function variationUpload( $variation_data = array(), $listing_id ) {
            /**
             * $preparedVariation = $this->testSinglyVariatedFormat();
             * $preparedVariation = $this->getpreparedVariationDataforEtsy($variation_data, $listing_id, $first_upload);
             * $preparedVariation = $this->getVariationDataforEtsy($variation_data, $listing_id);
             */

            $preparedVariation = $variation_data;
            global $wpdb;
            $table = $wpdb->prefix . 'etcpf_listings';
            if ( is_array( $preparedVariation ) || count( $preparedVariation ) > 0 && ( isset( $preparedVariation['data'] ) && $preparedVariation['data'] !== 'MORE_THAN_TWO_VARIATION_ATTRIBUTES' ) ) {
                $this->updateApiLimitVal( get_option( 'etsy_api_count' ) );
                $url     = 'https://openapi.etsy.com/v3/application/listings/' . $listing_id . '/inventory';
                $acc_req = $this->prepareHash( $url, 'PUT', $type = true );
                $response = wp_remote_request(
                    $acc_req,
                    array(
                        'timeout'     => 120,
                        'redirection' => 5,
                        'blocking'    => true,
                        'method' => 'PUT',
                        'headers'     => array(
                            'Content-Type' => 'application/json',
                            'Expect'       => '',
                            'x-api-key'     => get_option( 'etcpf_api_key' ),
                            'Authorization' => 'Bearer '
                                . get_option( 'etcpf_oauth_token' )

                        ),
                        'body'        => json_encode($preparedVariation),
                    )
                );

                $response = wp_remote_retrieve_body( $response );

                if ( $response ) {
                    $data = array(
                        'variation_upload_result' => $response,
                        'uploaded'                => 2, /* Uploading process completed successfully */
                    );
                    $wpdb->update( $table, $data, array( 'listing_id' => $listing_id ) );
                    $VarResult = json_decode( $response );
                    if ( $VarResult ) {
                        if ( is_array( $VarResult->products ) ) {
                            $this->variation_upload_message = count( $VarResult->products ) . ' variation uploaded in etsy';
                        }

                        return $VarResult;
                    } else {
                        $this->variation_upload_message = $response;

                        return true;
                    }
                } else {
                    $data = array(
                        'variation_upload_result' => json_encode(
                            array(
                                'count'    => 0,
                                'result'   => 'Something went wrong on variation upload. You can reupload variations while listing upload is completed. Thanks.',
                                'uploaded' => 2, /* Uploading process completed successfully */
                            )
                        ),
                        'uploaded'                => 2, /* Uploading process completed successfully */
                    );
                    $wpdb->update( $table, $data, array( 'listing_id' => $listing_id ) );
                    $this->variation_upload_message = "There was some problem uploading variation of this item, which etsy did not provide in response. Please try again later or <a href='https://www.exportfeed.com/contact'>contact</a> us";

                    return true;
                }
            } else {
                $data = array(
                    'variation_upload_result' => json_encode(
                        array(
                            'count'  => 0,
                            'result' => 'Etsy supports only two variation attributes. It looks like you have more than two variation attributes. You better select alternative upload type and reuplad the product. Thank you.',
                        )
                    ),
                    'uploaded'                => 2, /* Uploading process completed successfully */
                );
                $wpdb->update( $table, $data, array( 'listing_id' => $listing_id ) );
                $this->variation_upload_message = 'Etsy supports only two variation attributes. It looks like you have more than two variation attributes. You better select alternative upload type and reuplad the product. Thank you.';
            }

            return null;
        }

        public function testSinglyVariatedFormat( $pid, $feed_id, $profileid ) {
            $product              = new WC_Product_Variable( $pid );
            $invoker              = new ETCPF_singleVariationUploadManager();
            $available_variations = $product->get_available_variations();
            if ( is_array( $available_variations ) && count( $available_variations ) > 0 ) {
                $EtsyFormatVariation = $invoker->getFormattedVariation( $available_variations, $feed_id, $profileid );
                $data                = array(
                    'data' => array(
                        'products'             => json_encode( $EtsyFormatVariation ),
                        'price_on_property'    => array( 513 ),
                        'quantity_on_property' => array( 513 ),
                        'sku_on_property'      => array( 513 ),
                    ),
                );

                return $data;
            }

            return false;
        }

        public function listing_report( $feedId ) {
            /*
             *
             * @Todo: Shoulod be implemented later
            * */
            echo '<pre>';
            print_r( 'came' );
            echo '</pre>';
            exit();
        }

        public function makelistingUnuploaded( $itemid, $id, $type ) {
            global $wpdb;
            $table = $wpdb->prefix . 'etcpf_listings';
            $wpdb->update(
                $table,
                array( 'uploaded' => 6 ),
                array(
                    'id'      => $id,
                    'feed_id' => $itemid,
                )
            );
            if ( ! $wpdb->last_error ) {
                return true;
            }

            return false;
        }

        public function submissionCheck( $sku ) {
            global $wpdb;
            $syncTable = $wpdb->prefix . 'etcpf_etsy_sync';
            // $table = $wpdb->prefix . 'etcpf_listings';
            if ( $sku ) {
                $data = $wpdb->get_var(
                    $wpdb->prepare( "SELECT listing_id FROM $syncTable WHERE sku=%s", array( $sku ) )
                );
                if ( $data ) {
                    return $data;
                } else {
                    return false;
                }
            }

            return false;
        }


        public function generateRandomString( $length = 10 ) {
            $characters       = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen( $characters );
            $randomString     = '';
            for ( $i = 0; $i < $length; $i ++ ) {
                $randomString .= $characters[ rand( 0, $charactersLength - 1 ) ];
            }

            return $randomString;
        }


        // This function updates order shipment details from woocommerce to etsy if the detail is not added

        public function update_etsy_order_shipment( $order_id ): bool {
            require_once 'order_fulfillment.php';

            $fulfill       = new Order_FulFillment();
            $shop_id       = get_option( 'etcpf_shop_id' );
            $order         = wc_get_order( $order_id );
            $receipt_id    = $order->get_meta( 'etcpf_etsy_receipt_id' );
            $url           = 'https://api.etsy.com/v3/application/shops/' . $shop_id . '/receipts/' . $receipt_id . '/tracking';
            $tracking_meta = get_post_meta( $order_id, '_wc_shipment_tracking_items' );

            // if no receipt id no need to proceed ahead
            if ( empty( $receipt_id ) ) {
                return false;
            }

            // check if the receipt contains any shipment. If so then return false
            if ( $this->check_if_order_contains_any_shipment( $shop_id, $receipt_id ) ) {
                return false;
            }

            if ( empty( $tracking_meta ) ) {
                return $fulfill->withoutTrackingDetails( $url );
            }

            return $fulfill->withTrackingDetails( $url, $tracking_meta[0][0]['tracking_number'], $tracking_meta[0][0]['tracking_provider'] );
        }

        public function _curlGetRequestV3( $url ) {
            $response = wp_remote_get(
                $url,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'Expect'        => '',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer ' . get_option( 'etcpf_oauth_token' )
                    ),
                ) );

            return json_decode( wp_remote_retrieve_body( $response ), true );

        }

        /**
         * Checks it the receipt id has any shipment. If it contains then it returns true
         *
         * @param $shop_id
         * @param $receipt_id
         *
         * @return bool
         */
        public function check_if_order_contains_any_shipment( $shop_id, $receipt_id ): bool {

            $url = 'https://api.etsy.com/v3/application/shops/' . $shop_id . '/receipts/' . $receipt_id;

            $response     = wp_remote_get(
                $url,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'Expect'        => '',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer ' . get_option( 'etcpf_oauth_token' )
                    ),
                ) );
            $api_response = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( ! $api_response['is_shipped'] ) {
                return false;
            }

            return true;
        }
        public function get_listings_translations($listing_id, $params){
            $query = http_build_query($params);
            $url = 'https://openapi.etsy.com/v3/application/listings/' . $listing_id . '?' ;
            $url = $url . $query;
            $response = wp_remote_get(
                $url,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer ' . get_option( 'etcpf_oauth_token' )
                    ),
                )
            );
            if ( wp_remote_retrieve_response_code( $response ) == 200) {
                $result  = json_decode( wp_remote_retrieve_body( $response ) );
                return $result;
            }
        }

        public function get_receipt_details( $receipt_id ) {
            $shop_id = get_option( 'etcpf_shop_id' );
            $url     = 'https://api.etsy.com/v3/application/shops/' . $shop_id . '/receipts/' . $receipt_id;

            $response = wp_remote_get(
                $url,
                array(
                    'timeout'     => 120,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'Expect'        => '',
                        'x-api-key'     => get_option( 'etcpf_api_key' ),
                        'Authorization' => 'Bearer ' . get_option( 'etcpf_oauth_token' )
                    ),
                ) );

            return json_decode( wp_remote_retrieve_body( $response ), true );
        }
    } /*End of Class*/

}
