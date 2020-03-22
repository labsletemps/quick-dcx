<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(Request $request)
    {
        $pub_date = '_pub_date:[NOW{{$solr_date_rev_offset}}/DAY{{$solr_date_offset}} TO NOW{{$solr_date_rev_offset}}/DAY+1DAY{{$solr_date_offset}}-1MILLISECOND]';
        if ('+1' === $request->get('delta')) {
          $pub_date = '_pub_date:[NOW{{$solr_date_rev_offset}}/DAY+1DAY{{$solr_date_offset}} TO NOW{{$solr_date_rev_offset}}/DAY+2DAY{{$solr_date_offset}}-1MILLISECOND]';
        }
        $uri = "https://mdb.ringier.ch/dcx_rng/atom/documents";
        $qs = ['q' => [
              'channel' => [0 => 'channel_pool_story_lausanne'],
              'simple' => [
                  'sort_pubinfo_storys' => '_sort_:"_lastmodified desc"',
                  'storytype_la' => 'StoryType:"DigitalLA"',
                  'pubinfo_date' => $pub_date,
                ],
              'content_view' => 'text_view_thumb',
            ],
        ];

        $client = HttpClient::create();
        $response = $client->request('GET', $uri, [
          'auth_basic' => [$this->getParameter('app.mdb.username'), $this->getParameter('app.mdb.password')],
          'query' => $qs
        ]);

        $stories = $statuses = [];
        if (200 === $response->getStatusCode()) {
          $xml = new \SimpleXMLElement($response->getContent());
          foreach ($xml->entry  as $entry) {
            $status = (string) $entry->document->task->attributes()['topic'];
            $title = (string) $entry->title ?? NULL;
            $creator = (string) $entry->document->head->Creator;
            $id = (string) $entry->id;

            $story = [
              'status' => $status,
              'title' => $title,
              'creator' => $creator
            ];

            $stories[$status][$id] = $story;
          }
        }

        return $this->render('index/index.html.twig', [
            'stories' => $stories,
        ]);
    }
}
