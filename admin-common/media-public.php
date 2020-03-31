<?php
/*
          Inroads Control Panel/Shopping Cart - Public Media Functions

                     Written 2014-2018 by Randall Severy
                      Copyright 2014-2018 Inroads, LLC
*/

if (file_exists('engine/db.php')) {
   require_once 'engine/ui.php';
   require_once 'engine/db.php';
}
else {
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
}
require_once 'media-common.php';

class MediaLibrary {

function __construct($library_id,$db=null)
{
    global $docroot;

    $this->user = null;
    $this->id = $library_id;
    $this->docroot = $docroot;
    if ($db) $this->db = $db;
    else $this->db = new DB;

    $query = 'select * from media_libraries where id=?';
    $query = $this->db->prepare_query($query,$library_id);
    $this->info = $this->db->get_record($query);
    if (! $this->info) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       else $this->error = 'Media Library #'.$library_id.' not found';
       return null;
    }

    if (empty($this->info['cookie_name'])) $this->user = null;
    else $this->user = get_cookie($this->info['cookie_name']);
}

function MediaLibrary($library_id,$db=null)
{
    self::__construct($library_id,$db);
}

function logged_in()
{
    if ($this->user) return true;
    return false;
}

function validate_login($username,$password)
{
    $this->user = null;
    if (! $username) return false;
    $query = 'select id,password from media_users where (library=?) and ' .
             '(username=?)';
    $query = $this->db->prepare_query($query,$this->id,$username);
    $row = $this->db->get_record($query);
    if (! $row) return false;
    if ($row['password'] != $password) return false;
    setcookie($this->info['cookie_name'],$row['id'],time() + 86400,'/');
    $this->user = $row['id'];
    return true;
}

function logout()
{
    setcookie($this->info['cookie_name'],'',time()-(3600 * 25),'/');
    $this->user = null;
}

function get_user_info()
{
    if (! $this->user) return null;
    $query = 'select * from media_users where (library=?) and (id=?)';
    $query = $this->db->prepare_query($query,$this->id,$this->user);
    $row = $this->db->get_record($query);
    return $row;
}

function add_user($username,$password,$firstname,$lastname,$email)
{
    $user_record = user_record_definition();
    $user_record['library']['value'] = $this->id;
    $user_record['username']['value'] = $username;
    $user_record['password']['value'] = $password;
    $user_record['firstname']['value'] = $firstname;
    $user_record['lastname']['value'] = $lastname;
    $user_record['email']['value'] = $email;
    if (! $this->db->insert('media_users',$user_record)) return false;
    log_activity('Added User '.$username.' (#'.$this->db->insert_id().')');
    return true;
}

function update_user($id,$username,$password,$firstname,$lastname,$email)
{
    $user_record = user_record_definition();
    $user_record['id']['value'] = $id;
    $user_record['username']['value'] = $username;
    $user_record['password']['value'] = $password;
    $user_record['firstname']['value'] = $firstname;
    $user_record['lastname']['value'] = $lastname;
    $user_record['email']['value'] = $email;
    if (! $this->db->update('media_users',$user_record)) return false;
    log_activity('Updated User '.$username.' (#'.$id.')');
    return true;
}

function delete_user($id)
{
    $user_record = user_record_definition();
    $user_record['id']['value'] = $id;
    if (! $this->db->delete('media_users',$user_record)) return false;
    log_activity('Deleted User #'.$id);
    return true;
}

function load_section($section_id)
{
    $query = 'select * from media_sections where id=?';
    $query = $this->db->prepare_query($query,$section_id);
    $section = $this->db->get_record($query);
    if (! $section) {
       if (isset($this->db->error)) return null;
       return array();
    }
    return $section;
}

function load_documents($section=null)
{
    $query = 'select d.* from media_documents d';
    if ($section) {
       $query .= ' join media_section_docs sd on sd.related_id=d.id where ' .
                 'sd.parent=? order by sd.sequence,d.filename';
       $query = $this->db->prepare_query($query,$section);
    }
    else {
       $query .= ' where library=? order by d.filename';
       $query = $this->db->prepare_query($query,$this->id);
    }
    $documents = $this->db->get_records($query,'id');
    if (! $documents) {
       if (isset($this->db->error)) return null;
       return array();
    }
    while (list($id,$row) = each($documents)) {
       $full_filename = $this->docroot.$this->info['doc_dir'].'/' .
                        $row['filename'];
       $documents[$id]['size'] = @filesize($full_filename);
    }
    reset($documents);
    return $documents;
}

function load_sections($load_all_documents=false,$top_level_only=false)
{
    $query = 'select * from media_sections where (library=?)';
    if ($top_level_only)
       $query .= ' and (id not in (select related_id from media_subsections))';
    $query .= ' order by sequence';
    $query = $this->db->prepare_query($query,$this->id);
    $sections = $this->db->get_records($query);
    if (! $sections) {
       if (isset($this->db->error)) return null;
       return array();
    }
    if (! $load_all_documents) return $sections;

    $query = 'select * from media_section_docs where parent in (select id ' .
             'from media_sections where library=?)';
    $query = $this->db->prepare_query($query,$this->id);
    $section_docs = $this->db->get_records($query);
    $documents = $this->load_documents();
    while (list($index,$section) = each($sections)) {
       $section_id = $section['id'];
       $docs = array();
       if ($section_docs) foreach ($section_docs as $section_doc) {
          if ($section_doc['parent'] != $section_id) continue;
          $doc_id = $section_doc['related_id'];
          if (isset($documents[$doc_id])) $docs[] = $documents[$doc_id];
       }
       $sections[$index]['documents'] = $docs;
    }
    reset($sections);
    return $sections;
}

function load_subsections($section_id=null,$load_all_subsections=false,
                          $load_all_documents=false)
{
    $query = 'select m.* from media_subsections s left join ' .
             'media_sections m on m.id=s.related_id';
    if ($section_id) $query .= ' where s.parent=?';
    else $query .= ' where m.library=?';
    $query .= ' order by s.sequence,m.name';
    if ($section_id) $query = $this->db->prepare_query($query,$section_id);
    else $query = $this->db->prepare_query($query,$this->id);
    $subsections = $this->db->get_records($query);
    if (! $subsections) {
       if (isset($this->db->error)) return null;
       return array();
    }
    if ($section_id && $load_all_subsections) {
       $new_sections = $subsections;
       while ($new_sections) {
          $ids = array();
          foreach ($subsections as $subsection) $ids[] = $subsection['id'];
          $query = 'select m.* from media_subsections s left join ' .
                   'media_sections m on m.id=s.related_id where (s.parent ' .
                   'in (?)) and (m.id not in (?)) order by s.sequence,m.name';
          $query = $this->db->prepare_query($query,$ids,$ids);
          $new_sections = $this->db->get_records($query);
          if ($new_sections)
             $subsections = array_merge($subsections,$new_sections);
          else if (isset($this->db->error)) return null;
       }
    }
    if (! $load_all_documents) return $subsections;

    $ids = array();
    foreach ($subsections as $subsection) $ids[] = $subsection['id'];
    $query = 'select * from media_section_docs where parent in (?)';
    $query = $this->db->prepare_query($query,$ids);
    $section_docs = $this->db->get_records($query);
    if (! $section_docs) return $subsections;
    $documents = $this->load_documents();
    reset($subsections);
    while (list($index,$section) = each($subsections)) {
       $section_id = $section['id'];
       $docs = array();
       foreach ($section_docs as $section_doc) {
          if ($section_doc['parent'] != $section_id) continue;
          $doc_id = $section_doc['related_id'];
          if (isset($documents[$doc_id])) $docs[] = $documents[$doc_id];
       }
       $subsections[$index]['documents'] = $docs;
    }
    reset($subsections);
    return $subsections;
}

function download_document($id)
{
    if (! $this->user) return false;
    if (! is_numeric($id)) return false;
    $query = 'select filename from media_documents where id='.$id;
    $row = $this->db->get_record($query);
    if (! $row) return false;
    $filename = $row['filename'];
    $full_filename = $this->docroot.$this->info['doc_dir'].'/'.$filename;
    $content = file_get_contents($full_filename);
    if (! $content) return false;

    $query = 'insert into media_downloads (document,user,download_date) ' .
             'values('.$id.','.$this->user.','.time().')';
    if (! $this->db->query($query)) return false;

    if (get_browser_type() == MSIE)
       header('Content-type: application/inroads');
    else header('Content-type: application/octet-stream');
    header('Content-disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: no-cache');
    header('Expires: -1441');
    print $content;
}

};

?>
