

// namespace Drupal\csvapi\Service;

// use Drupal\Core\Queue\QueueFactory;

// class CSVAPIService {
//     protected $queue;

//     public function __construct(QueueFactory $queue_factory) {
//         // Create or load the specific queue.
//         $this->queue = $queue_factory->get('csv_processing_queue');
//     }

//     public function enqueueFile($file) {
//         // Add the file item to the queue.
//         $this->queue->createItem(['file_id' => $file->id()]);
//     }
//}
