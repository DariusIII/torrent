<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 5-12-13
 * Time: 18:22
 */

namespace Devristo\Torrent;

class Torrent {
	/**
	 * @var
	 */
	private $data;

    /**
     * @var File[]
     */
    private array $files;

    protected function __construct($data){
        $this->data = $data;

        if(!$this->isValid())
            throw new \InvalidArgumentException("Invalid torrent data structure");

        if(!array_key_exists('files', $this->data['info'])){
            $this->files = array(new File($this->data['info']));
        } else {
            $this->files = array();

            foreach($this->data['info']['files'] as &$data){
                $this->files[] = new File($data);
            }
        }
    }

    public function getInfoHash($rawOutput=true): string
    {
        $bee = new Bee();
        return sha1($bee->encode($this->data['info']), $rawOutput);
    }

    protected function isValid(): bool
    {
		$hasKeys = function($keys, $data){
			if (is_array($data) && is_array($keys)) {
				return count(array_diff($keys, array_keys($data))) === 0;
			}
			return false;
        };

        if(!$hasKeys(array('info'), $this->data)) {
	        return false;
        }

        if(!$hasKeys(array('piece length', 'pieces'), $this->data['info'])) {
	        return false;
        }

        return true;
    }

    public function getAnnounce(){
        return array_key_exists('announce', $this->data) ? $this->data['announce'] : null;
    }

    public function setAnnounce($url): void
    {
        $this->data['announce'] = $url;
    }

    public function setAnnounceList(array $urls): void
    {
        foreach($urls as $url)
            if(!is_array($url))
                throw new \InvalidArgumentException("Announce list should be an array of arrays");


        $this->data['announce-list'] = $urls;
    }

    public function getAnnounceList(){
        return array_key_exists('announce-list', $this->data) ? $this->data['announce-list'] : array();
    }

    public function getCreationDate(): ?\DateTime
    {
        if(!array_key_exists('creation date', $this->data))
            return null;

        $dt = new \DateTime();
        $dt->setTimestamp($this->data['creation date']);

        return $dt;
    }

    public function getComment(){
        if(!array_key_exists('comment', $this->data))
            return null;

        return $this->data['comment'];
    }

    public function setComment($comment): void
    {
        $this->data['comment'] = $comment;
    }

    public function getCreatedBy(){
        if(!array_key_exists('created by', $this->data))
            return null;

        return $this->data['created by'];
    }

    public function getName(){
        return $this->data['info']['name'];
    }

    public function setName($name): void
    {
        $this->data['info']['name'] = $name;
    }

    public function setPrivate($val): void
    {
        $this->data['info']['private'] = $val ? 1 : 0;
    }

    public function getNumPieces(): float
    {
        return ceil($this->getSize() / $this->getPieceSize());
    }

    public function getPieces(): array
    {
        return str_split($this->data['info']['pieces'], 20);
    }

    public function getPieceSize(){
        return $this->data['info']['piece length'];
    }

    public function isPrivate(): bool
    {
        return array_key_exists('private', $this->data['info']);
    }

    public function getSize(){
        $length = 0;
        foreach($this->files as $file)
            $length += $file->getSize();

        return $length;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getFileTree(){
        $tree = array();

        // Create the tree
        foreach($this->getFiles() as $file){
            $start = &$tree;
            foreach($file->getParentDirectories() as $dir){
                $start = &$start[$dir];
            }

            $start[$file->getName()] = $file;
        }


        // Sort the tree, recursively, depth first search
        $to_sort = array(&$tree);
        while(count($to_sort)){
            $array = &$to_sort[count($to_sort)-1];
            array_pop($to_sort);

            // Sort current 'view'
            uksort($array, function($keyA, $keyB) use($array){
                $a = $array[$keyA];
                $b = $array[$keyB];

                // Order 2 directories according to their name
                if(is_array($a) && is_array($b))
                    return strcasecmp($keyA, $keyB);

                // Order the directory above the file
                elseif(is_array($a) && !is_array($b))
                    return -1;

                // Order the directory above the file
                elseif(!is_array($a) && is_array($b))
                    return 1;

                // Order 2 files according to their name
                else
                    return strcasecmp($a->getName(), $b->getName());
            });

            foreach($array as $k => $item)
                if(is_array($item))
                    $to_sort[] = &$item;
        }
        return $tree;
    }

    public function toArray(){
        return $this->data;
    }

    public function serialize(): string
    {
        $bee = new Bee();
        return $bee->encode($this->data);
    }

    public static function fromFile($filename): Torrent
    {
        $contents = file_get_contents($filename);

        return self::fromString($contents);
    }

    public static function fromString($string): Torrent
    {
        $bee = new Bee();
        $decoded = $bee->decode($string);
	    
	    return new Torrent($decoded);
    }
}