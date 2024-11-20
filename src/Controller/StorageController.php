<?php

declare(strict_types=1);

namespace Drupal\storage\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * StorageController class.
 */
class StorageController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * Route title callback
   *
   * @return string
   */
  public function getTitle(): TranslatableMarkup {
    return $this->t('Scorecard!');
  }

  /**
   * Display the markup.
   *
   * @return array
   */
  public function content() {
    return [
      '#theme' => 'storage',
      '#attached' => [
        'library' => [
          'storage/axios',
          'storage/vue',
          'storage/storage',
        ],
      ],
    ];
  }

  /**
   * Return a JSON representation of the facets tree
   *
   */
  private function createfacettree() {
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree("facets", 0, NULL, TRUE);
    //  $vid, $parent, $max_depth, $load_entities);

    // extract data for all of the terms
    foreach ($terms as $term) {
      if ($term->isPublished()) {
        if (sizeof($term->get('field_control_type')->getValue()) > 0) {
          $tid = $term->get('field_control_type')->getValue()[0]["target_id"];
          $control_type = Term::load($tid)->getName();
        }
        else {
          $control_type = NULL;
        }
        $term_data[] = [
          'id' => $term->tid->value,
          'name' => $term->name->value,
          "control_type" => $control_type,
          'parent' => $term->parents[0], // there will only be one
          'weight' => $term->weight->value,
          'selected' => FALSE,
          'description' => $term->getDescription(),
          'published' => $term->status->value,
        ];
      }
    }

    // find the questions and add choices array
    $questions = [];

    foreach ($term_data as $td) {
      if ($td["parent"] == "0") {
        $td["choices"] = [];
        array_push($questions, $td);
      }
    }

    $temp_questions = [];
    // get the facets for each of the questions
    foreach ($questions as $q) {
      foreach ($term_data as $td) {
        if ($td["parent"] == $q["id"]) {
          array_push($q["choices"], $td);
        }
      }
      // sort the choices by weight ascending
      $weight = [];
      foreach ($q["choices"] as $key => $row) {
        $weight[$key] = $row["weight"];
      }
      array_multisort($weight, SORT_ASC, $q["choices"]);
      array_push($temp_questions, $q);
    }

    $questions = $temp_questions;

    // sort the questions by weight
    $weight = [];
    foreach ($questions as $key => $row) {
      $weight[$key] = $row["weight"]; // convert to number
    }
    array_multisort($weight, SORT_ASC, $questions);
    return $questions;
  }

  public function facettree() {
    $questions = $this->createfacettree();
    return new JsonResponse($questions);
  }

  private function createservicelist() {
    $values = [
      'type' => 'service',
    ];

    $nodes = \Drupal::entityTypeManager()
      ->getListBuilder('node')
      ->getStorage()
      ->loadByProperties($values);

    $services = []; // where we will build the service data

    foreach ($nodes as $node) {
      $s = [];
      $s["id"] = $node->id();
      $s["title"] = $node->getTitle();
      // get the facet matches
      $s["facet_matches"] = [];
      foreach ($node->field_facet_matches as $match) {
        $s["facet_matches"][] = $match->target_id;
      }
      // get the service_paragraphs

      $paragraph = $node->get('field_service_paragraphs')->first();
      if ($paragraph) {
        $paragraph = $paragraph->get('entity')->getTarget();
        //var_dump($paragraph->get("field_access_and_collaboration"));exit;
        $s["Brief Description"] =
          $paragraph->get("field_brief_description")->getValue()[0]["value"];
        $s["Example Use"] = $paragraph->get("field_example_use")
          ->getValue()[0]["value"];
        $s["Cost"] = $paragraph->get("field_cost")->getValue()[0]["value"];
        $s["Capacity"] = $paragraph->get("field_capacity")
          ->getValue()[0]["value"];
        $s["Access and Collaboration"] =
          $paragraph->get("field_access_and_collaboration")
            ->getValue()[0]["value"];
        $s["Data Allowed"] = $paragraph->get("field_data_allowed")
          ->getValue()[0]["value"];
        $s["Durability"] = $paragraph->get("field_durability")
          ->getValue()[0]["value"];
        $s["Complexity"] = $paragraph->get("field_complexity")
          ->getValue()[0]["value"];
        $s["Contact"] = $paragraph->get("field_contact")
          ->getValue()[0]["value"];
      }

      $services[] = $s;

      return $services;
    }
  }

  public function servicelist() {
    $services = $this->createservicelist();

    return new JsonResponse($services);
  }

  private function createtestservicelist() {
    $values = [
      'type' => 'service',
    ];

    $nodes = \Drupal::entityTypeManager()
      ->getListBuilder('node')
      ->getStorage()
      ->loadByProperties($values);

    $services = []; // where we will build the service data

    $paragraph_display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load("paragraph.service_paragraphs.default");

    /** @var \Drupal\node\Entity\Node $node */
    foreach ($nodes as $node) {
      if ($node->isPublished()) {
        $s = [];
        $s["id"] = $node->id();
        $s["title"] = $node->getTitle();
        // get the facet matches
        $s["facet_matches"] = [];
        foreach ($node->field_facet_matches as $match) {
          $s["facet_matches"][] = $match->target_id;
        }
        $s["summary"] = $node->field_summary->value;
        // get the service_paragraphs

        $paragraph = $node->get('field_service_paragraphs')->first();
        if ($paragraph) {
          $pdoutput = [];
          $paragraph = $paragraph->get('entity')->getTarget();

          // the order of the paragraphs is in $paragraph_display[
          // the fields are array_keys($paragraph_display["content"])
          // the weights are $paragraph_display["content"][$field]["weight"]

          $pdcontent = $paragraph_display->toArray()["content"];

          foreach ($pdcontent as $machine_name => $field_data) {
            $field_data = [];
            if (sizeof($paragraph->get($machine_name)->getValue()) > 0) {
              $field_data["value"] = $paragraph->get($machine_name)
                ->getValue()[0]["value"];
            }

            $field_config = \Drupal::entityTypeManager()
              ->getStorage('field_config')
              ->load("paragraph" . '.' . "service_paragraphs" . '.' . $machine_name)
              ->toArray();

            $field_data["label"] = $field_config["label"];
            $field_data["weight"] = $pdcontent[$machine_name]["weight"];

            $pdoutput[$machine_name] = $field_data;
          }
          $s["field_data"] = $pdoutput;
        }
        $services[] = $s;
      }
    }

    $title = [];
    foreach ($services as $key => $row) {
      $title[$key] = $row["title"];
    }
    array_multisort($title, SORT_ASC | SORT_NATURAL | SORT_FLAG_CASE, $services);

    return $services;
  }

  public function testservicelist() {
    $services = $this->createtestservicelist();
    return new JsonResponse($services);
  }

  public function send_email() {
    if (\Drupal::service('session')->isStarted() === FALSE) {
      return new JsonResponse("no session, so sorry");
    }
    //$url = \Drupal::request()->getSchemeAndHttpHost().
    //        "/session/token";
    //$desiredtoken = $this->get_web_page($url);

    //$desired_token = session_id();
    //$desired_token = Drupal::csrfToken()->get();

    //$intoken = \Drupal::request()->headers->get("X-CSRF-Token");

    //return new JsonResponse(["want $desired_token got $intoken"]);

    // data include name, email, facets (string)
    $json_string = \Drupal::request()->getContent();
    //  \Drupal::logger('storage')->notice("email json is $json_string");

    $decoded = \Drupal\Component\Serialization\Json::decode($json_string);

    // get $qdata from $decoded
    $qdata = $decoded["qdata"];
    // get $sdata from $decoded
    $sdata = $decoded["sdata"];

    $body = "Thank you for using the Service Finder. " .
      "We hope it was useful.\r\n\r\n" .
      "Your selected criteria were:\r\n";

    $questions = $this->createfacettree();

    $facets = [];

    foreach ($qdata as $qitem) {
      $question_id = $qitem[0];
      $facet_id = $qitem[1];
      $facets[] = $facet_id;
      foreach ($questions as $question) {
        if ($question["id"] == $question_id) {
          $body = $body . "* " . $question["name"] . " -- ";
          foreach ($question["choices"] as $choice) {
            if ($choice["id"] == $facet_id) {
              $body = $body . $choice["name"] . "\r\n";
            }
          }
        }
      }
    }

    $body = $body . "\r\nYour resulting choices were:\r\n";

    $services = $this->createtestservicelist();

    foreach ($sdata as $svc) {
      foreach ($services as $service) {
        if ($service["id"] == $svc) {
          $body = $body . "* " . $service["title"] . "\r\n";
        }
      }
    }

    $body = $body . "\r\nUse this link to return to the tool " .
      "with your criteria already selected: " .
      \Drupal::request()->getSchemeAndHttpHost() .
      "/storage?facets=" .
      implode(",", $facets) .
      "\r\n\r\n" .
      "If you have any further questions or need more information about " .
      "the Service Finder options, please contact the Service team at " .
      "user@example.com to set up a consultation, " .
      "or contact the service owners " .
      "directly (contact details in tool comparison table).\r\n\r\n" .
      "Visit also the Service website at http://example.com ";

    $subject = "Assistance request from Service Finder";

    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = "storage";
    $key = 'complete_form';

    $to = $decoded['email'];
    $params['message'] = $body;
    $params['subject'] = "ABC";

    \Drupal::logger('storage')->notice("to is $to");
    \Drupal::logger('storage')->notice("message is {$params['message']}");

    //$params['node_title'] = $entity->label();

    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;
    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

    if ($result['result'] !== TRUE) {
      // drupal_set_message(t('There was a problem sending your message and it was not sent.'), 'error');
      \Drupal::messenger()
        ->addMessage($this->t('There was a problem sending your message and it was not sent.'), 'error');
      return new JsonResponse("problem");
    }
    else {
      // drupal_set_message(t('Your message has been sent.'));
      \Drupal::messenger()->addMessage($this->t('Your message has been sent.'));
      return new JsonResponse("success");
    }
  }

  public function configuration() {
    if (\Drupal::service('session')->isStarted() === FALSE) {
      \Drupal::service('session')->start();
      \Drupal::service('session')->set('foo', 'bar');
    }
    \Drupal::logger('storage')->notice("starting session.");

    $config = \Drupal::service('config.factory')
      ->getEditable("storage.settings");
    $data = [];
    $data["title"] = $config->get("title");
    $data["subtitle"] = $config->get("subtitle");
    $data["question_header"] = $config->get("question_header");
    $data["service_header"] = $config->get("service_header");
    $data["chart_header"] = $config->get("chart_header");
    $data["email_form_header"] = $config->get("email_form_header");
    $data["email_address"] = $config->get("email_address");
    $data["email_name"] = $config->get("email_name");
    return new JsonResponse($data);
  }

  private function get_web_page($url) {
    $options = [
      CURLOPT_RETURNTRANSFER => TRUE,   // return web page
      CURLOPT_HEADER => FALSE,  // don't return headers
      CURLOPT_FOLLOWLOCATION => TRUE,   // follow redirects
      CURLOPT_MAXREDIRS => 10,     // stop after 10 redirects
      CURLOPT_ENCODING => "",     // handle compressed
      CURLOPT_USERAGENT => "test", // name of client
      CURLOPT_AUTOREFERER => TRUE,   // set referrer on redirect
      CURLOPT_CONNECTTIMEOUT => 120,    // time-out on connect
      CURLOPT_TIMEOUT => 120,    // time-out on response
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);

    $content = curl_exec($ch);

    curl_close($ch);

    return $content;
  }

}
