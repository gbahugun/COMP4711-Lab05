<?php

class Tasks extends XML_MODEL
{
    protected $uncompleted;
    protected $completed;
    private $CI; // use this to reference the CI instance

    public function __construct() {
        parent::__construct(APPPATH . '../data/tasks.xml', 'id');
        $this->CI = &get_instance(); // retrieve the CI instance
    }

    protected function load()
    {

      if (($tasks = simplexml_load_file($this->_origin)) !== FALSE)
      {
        foreach ($tasks as $task) {
          $record = new stdClass();
          $record->id = (int) $task->id;
          $record->desc = (string) $task->desc;
          $record->priority = (int) $task->priority;
          $record->size = (int) $task->size;
          $record->group = (int) $task->group;
          $record->deadline = (string) $task->deadline;
          $record->status = (int) $task->status;
          $record->flag = (int) $task->flag;

          $this->_data[$record->id] = $record;
        }
      }

      // rebuild the keys table
      $this->reindex();

      if (file_exists(realpath($this->_origin))) {

          $this->xml = simplexml_load_file(realpath($this->_origin));
          if ($this->xml === false) {
              // error so redirect or handle error
              header('location: /404.php');
              exit;
        }

          $xmlarray =$this->xml;

          //if it is empty;
          if(empty($xmlarray)) {
            return;
          }

          //get all xmlonjects into $xmlcontent
          $rootkey = key($xmlarray);
          $xmlcontent = (object)$xmlarray->$rootkey;

          $keyfieldh = array();
          $first = true;

          //if it is empty;
          if(empty($xmlcontent)) {
            return;
          }

          $dataindex = 1;
          $first = true;
          foreach ($xmlcontent as $oj) {
            if($first){
              foreach ($oj as $key => $value) {
                $keyfieldh[] = $key;
                //var_dump((string)$value);
              }
              $this->_fields = $keyfieldh;
            }
            $first = false;

            //var_dump($oj->children());
            $one = new stdClass();

            //get objects one by one
            foreach ($oj as $key => $value) {
              $one->$key = (string)$value;
            }
            $this->_data[$dataindex++] =$one;
          }


        //var_dump($this->_data);
      } else {
          exit('Failed to open the xml file.');
      }

      // --------------------
      // rebuild the keys table
      $this->reindex();
    }

    protected function store()
    {
        if (($handle = fopen($this->_origin, "w")) !== FALSE)
        {
            $xmlDoc = new DOMDocument( "1.0");
            $xmlDoc->preserveWhiteSpace = false;
            $xmlDoc->formatOutput = true;
            $data = $xmlDoc->createElement($this->xml->getName());
            foreach($this->_data as $key => $value)
            {
                $task  = $xmlDoc->createElement('task');
                $id = $xmlDoc->createElement('id', htmlspecialchars($value->id));
                $desc = $xmlDoc->createElement('desc', htmlspecialchars($value->desc));
                $priority = $xmlDoc->createElement('priority', htmlspecialchars($value->priority));
                $size = $xmlDoc->createElement('size', htmlspecialchars($value->size));
                $group = $xmlDoc->createElement('group', htmlspecialchars($value->group));
                $deadline = $xmlDoc->createElement('deadline', htmlspecialchars($value->deadline));
                $status = $xmlDoc->createElement('status', htmlspecialchars($value->status));
                $flag = $xmlDoc->createElement('flag', htmlspecialchars($value->flag));

                $task->appendChild($id);
                $task->appendChild($desc);
                $task->appendChild($priority);
                $task->appendChild($size);
                $task->appendChild($group);
                $task->appendChild($deadline);
                $task->appendChild($status);
                $task->appendChild($flag);

                $data->appendChild($task);
            }
            $xmlDoc->appendChild($data);
            $xmlDoc->saveXML($xmlDoc);
            $xmlDoc->save($this->_origin);
        }
    }

    function add($record) {
        parent:: add($record);
        foreach ($this->all() as $task) {
            if ($task->status != 2)
                $undone[] = $task;
        }
        $this->uncompleted = sizeof($undone);
        foreach ($this->all() as $task) {
            if ($task->status == 2)
                $done[] = $task;
        }
        $this->completed = sizeof($done);
        if ($this->completed > $this->uncompleted)
            throw new Exception('More completed than uncompleted.');
    }

    function getCategorizedTasks()
    {

        // extract the undone tasks
        foreach ($this->all() as $task) {
            if ($task->status != 2)
                $undone[] = $task;
        }

        // substitute the category name, for sorting
        foreach ($undone as $task)
            $task->group = $this->CI->app->group($task->group); // use CI to get at the app model

        // order them by category
        usort($undone, "orderByCategory");

        // convert the array of task objects into an array of associative objects
        foreach ($undone as $task)
            $converted[] = (array)$task;
        return $converted;
    }

    // provide form validation rules
    public function rules()
    {
        $config = array(
            ['field' => 'task', 'label' => 'TODO task', 'rules' => 'alpha_numeric_spaces|max_length[64]'],
            ['field' => 'priority', 'label' => 'Priority', 'rules' => 'integer|less_than[4]'],
            ['field' => 'size', 'label' => 'Task size', 'rules' => 'integer|less_than[4]'],
            ['field' => 'group', 'label' => 'Task group', 'rules' => 'integer|less_than[5]'],
        );
        return $config;
    }

}

// return -1, 0, or 1 of $a's category name is earlier, equal to, or later than $b's
function orderByCategory($a, $b)
{
    if ($a->group < $b->group)
        return -1;
    elseif ($a->group > $b->group)
        return 1;
    else
        return 0;
}
