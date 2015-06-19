<?php

if ( false === include_once( config_get( 'plugin_path' ) . 'Source/MantisSourcePlugin.class.php' ) ) {
	return;
}

require_once( config_get( 'core_path' ) . 'json_api.php' );

class SourceGitblitPlugin extends MantisSourcePlugin {

	const ERROR_INVALID_PRIMARY_BRANCH = 'invalid_branch';

	public function register() {
		$this->name = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );

		$this->version = '0.18';
		$this->requires = array(
			'MantisCore' => '1.2.16',
			'Source' => '0.18',
		);

		$this->author = 'Kévin Charrais';
		$this->contact = 'kevin.charrais@novia-systems.fr';
		$this->url = 'http://www.novia-systems.fr';
	}

	/*public function errors() {
		$t_errors_list = array(
			self::ERROR_INVALID_PRIMARY_BRANCH,
		);
		foreach( $t_errors_list as $t_error ) {
			$t_errors[$t_error] = plugin_lang_get( 'error_' . $t_error );
		}
		return $t_errors;
	}*/

	public $type = 'gitblit';

	public function show_type() {
		return plugin_lang_get( 'gitblit' );
	}

	public function show_changeset( $p_repo, $p_changeset ) {
		$t_ref = substr( $p_changeset->revision, 0, 8 );
		$t_branch = $p_changeset->branch;

		return "$t_branch $t_ref";
	}

	public function show_file( $p_repo, $p_changeset, $p_file ) {
		return  "$p_file->action - $p_file->filename";
	}

	public function url_repo( $p_repo, $p_changeset=null ) {
		$t_root = $p_repo->info['root'];
		$t_reponame = $p_repo->info['reponame'];
		//$t_username = $p_repo->info['username'];
		$t_ref = "";

		if ( !is_null( $p_changeset ) ) {
			$t_ref = "/$p_changeset->revision";
		}

		return "$t_root/tree/$t_reponame$t_ref";
	}

	public function url_changeset( $p_repo, $p_changeset ) {
		$t_root = $p_repo->info['root'];
		$t_reponame = $p_repo->info['reponame'];
		//$t_username = $p_repo->info['username'];
		$t_ref = $p_changeset->revision;

		return "$t_root/commit/$t_reponame/$t_ref";
	}

	public function url_file( $p_repo, $p_changeset, $p_file ) {
		$t_root = $p_repo->info['root'];
		$t_reponame = $p_repo->info['reponame'];
		$t_filename = $p_file->filename;

		return "$t_root/blob/$t_reponame/$t_filename";
	}

	public function url_diff( $p_repo, $p_changeset, $p_file ) {
		$t_root = $p_repo->info['root'];
		$t_reponame = $p_repo->info['reponame'];
		$t_ref = $p_changeset->revision;
		$t_filename = $p_file->filename;

		return "$t_root/compare/$t_reponame/$t_ref/$t_filename";
	}

	public function update_repo_form( $p_repo ) {
		$t_root = null;
		$t_reponame = null;	
		$t_username = null;
		
		if ( isset( $p_repo->info['root'] ) ) {
			$t_root = $p_repo->info['root'];
		}
		if ( isset( $p_repo->info['reponame'] ) ) {
			$t_reponame = $p_repo->info['reponame'];
		}
		if ( isset( $p_repo->info['username'] ) ) {
			$t_username = $p_repo->info['username'];
		}
		if ( isset( $p_repo->info['master_branch'] ) ) {
			$t_master_branch = $p_repo->info['master_branch'];
		} else {
			$t_master_branch = 'master';
		}
?>
<tr <?php echo helper_alternate_class() ?>>
<td class="category"><?php echo plugin_lang_get( 'root' ) ?></td>
<td><input name="root" maxlength="250" size="40" value="<?php echo string_attribute( $t_root ) ?>"/></td>
</tr>
<tr <?php echo helper_alternate_class() ?>>
<td class="category"><?php echo plugin_lang_get( 'reponame' ) ?></td>
<td><input name="reponame" maxlength="250" size="40" value="<?php echo string_attribute( $t_reponame ) ?>"/></td>
</tr>
<tr <?php echo helper_alternate_class() ?>>
<td class="category"><?php echo plugin_lang_get( 'username' ) ?></td>
<td><input name="username" maxlength="250" size="40" value="<?php echo string_attribute( $t_username ) ?>"/></td>
</tr>
<tr <?php echo helper_alternate_class() ?>>
<td class="category"><?php echo plugin_lang_get( 'master_branch' ) ?></td>
<td><input name="master_branch" maxlength="250" size="40" value="<?php echo string_attribute( $t_master_branch ) ?>"/></td>
</tr>
<?php
	}

	public function update_repo( $p_repo ) {
		$f_root = gpc_get_string( 'root' );
		$f_reponame = gpc_get_string( 'reponame' );
		$f_username = gpc_get_string( 'username' );
		$f_master_branch = gpc_get_string( 'master_branch' );

		if ( !preg_match( '/^(\*|[a-zA-Z0-9_\., -]*)$/', $f_master_branch ) ) {
			plugin_error( self::ERROR_INVALID_PRIMARY_BRANCH );
		}

		$p_repo->info['root'] = $f_root;
		$p_repo->info['reponame'] = $f_reponame;
		$p_repo->info['username'] = $f_username;
		$p_repo->info['master_branch'] = $f_master_branch;

		return $p_repo;
	}
	
	private function api_uri( $p_repo, $p_path ) {
		//$t_root = $p_repo->info['root'];
		//$t_uri = $t_root$p_path;

		/*if( isset( $p_repo->info['hub_app_secret'] ) ) {
			$t_access_token = $p_repo->info['hub_app_secret'];
			if ( !is_blank( $t_access_token ) ) {
				$t_uri .= '?private_token=' . $t_access_token;
			}
		}*/

		return $p_path;
	}

	public function precommit() {
		$f_payload = gpc_get_string( 'payload', null );
		if ( is_null( $f_payload ) ) {
			return;
		}

		if ( false === stripos( $f_payload, 'github.com' ) ) {
			return;
		}

		$t_data = json_decode( $f_payload, true );
		$t_reponame = $t_data['repository']['name'];

		$t_repo_table = plugin_table( 'repository', 'Source' );

		$t_query = "SELECT * FROM $t_repo_table WHERE info LIKE " . db_param();
		$t_result = db_query_bound( $t_query, array( '%' . $t_reponame . '%' ) );

		if ( db_num_rows( $t_result ) < 1 ) {
			return;
		}

		while ( $t_row = db_fetch_array( $t_result ) ) {
			$t_repo = new SourceRepo( $t_row['type'], $t_row['name'], $t_row['url'], $t_row['info'] );
			$t_repo->id = $t_row['id'];

			if ( $t_repo->info['reponame'] == $t_reponame ) {
				return array( 'repo' => $t_repo, 'data' => $t_data );
			}
		}

		return;
	}

	public function commit( $p_repo, $p_data ) {
		$t_commits = array();

		foreach( $p_data['commits'] as $t_commit ) {
			$t_commits[] = $t_commit['id'];
		}

		$t_refData = explode( '/',$p_data['ref'] );
		$t_branch = $t_refData[2];

		return $this->import_commits( $p_repo, $t_commits, $t_branch );
	}

	public function import_full( $p_repo ) {
		echo '<pre>';
		
		// Récup des config entrees par l'utilisateur sur mantis
		$t_root = $p_repo->info['root'];
		$t_reponame = $p_repo->info['reponame'];
		$t_branch = $p_repo->info['master_branch'];
		
		
		if ( is_blank( $t_branch ) ) {
			$t_branch = 'master';
		}

		if ($t_branch != '*')
		{
			$t_branches_allowed = array_map( 'trim', explode( ',', $t_branch ) );
		}
		
		// On recupere toutes les branches du depot
		$t_uri = "$t_root/rpc?req=LIST_BRANCHES";
		$t_member = null;
		$t_json = json_url( $t_uri, $t_member ); // transformation du json
		
		//var_dump($t_uri);
		//var_dump($t_json);

		// On garde le nom des branches
		$t_branches = array();
		for($i = 0; $i < count($t_json->{$t_reponame}); $i++)
		{
		
		  $namebranche = $t_json->{$t_reponame}[$i];
		  $t_branches[$i] = $namebranche;
		  //var_dump($namebranche);
		}
		
		var_dump($t_branches);
		
		// Tableau associant commit par branche
		// On associe chaque commit à une branche
		$branch_commit = array();
		$tabBranchesCommits = array();
		
		foreach($t_branches as $branche)
		{
			// On recup le flux RRS de la branche
			$t_rss = "$t_root/feed/$t_reponame?h=$branche";
			
			echo "Flux de la branche : ".$t_rss."\n";

			$rss = simplexml_load_file($t_rss);

			// On veut connaitre la branche de chaque ticket
			foreach ($rss->channel->item as $item)
			{
				foreach ($item->category as $category)
				{
				      //echo "cat = ".$category."\n";
				    
				      // On recup les commits de la branche
				      if(preg_match('/^commit:/',$category))
				      {
					$shaCommit = substr($category, 7);	//echo "commit = ".$shaCommit."\n";

					// On associe une branche à chaque commit
					$branch_commit[$shaCommit]  = $branche;
				      }
				}
			}
		}
		
		// Execution sur la tableau final $branch_commit où tout est OK
		// tableau contenant tous les commits par branche
		foreach($t_branches as $branche)
		{
		    $tab = array();
		    foreach($branch_commit as $key => $value)
		    {
			if($value == $branche)
			{
			    $tab[] = $key;
			}
		    }
	
		    $tabBranchesCommits[$branche] = $tab;
		}
		
		//echo "dump du tabBranchesCommits\n";
		//var_dump($tabBranchesCommits);
		
		$t_changesets = array();
		
		// Recupe du flux rss de la branche orpheline (journaux des tickets)
		// On recup la branche des journaux des tickets
		$t_rss = "$t_root/feed/$t_reponame?h=refs/meta/gitblit/tickets";
		
		//echo "Flux de la branche des tickets : ".$t_rss."\n";
		
		$rss = simplexml_load_file($t_rss);

		foreach ($rss->channel->item as $item)
		{
		    // On récupe le title de l'item pour connaitre le numéro du ticket (les titres ont otus la meme syntaxe #numticket)
		    $title = $item->title;
			
		    if(preg_match('/^#[0-9]+/',$title))
		    {
			$numTicket = substr($title, 1);

			foreach ($item->category as $category)
			{
			  if(preg_match('/^commit:/',$category))
			  {
			    $shaCommit = substr($category, 7);	//echo "commit = ".$shaCommit."\n";
			  }
			}
		    
			$repertory = $numTicket;
			
			if($numTicket <= 9)
			{
			    $repertory = "0".$numTicket;
			}

			// On recup le journal du ticket
			$journalTicket = "$t_root/raw/$t_reponame/$shaCommit/id/$repertory/$numTicket/journal.json";
			
			//echo "journal = ".$journalTicket."\n";

			$t_member = null;
			$t_jsonTicket = json_url( $journalTicket, $t_member );
			//echo "debut journal\n";
			//var_dump($t_jsonTicket);
			//echo "fin journal\n";
			
			// pour chaque jsonticket[i] on regarde le num commit
			// a partir du num, on recup la branche qui lui est associe => ne focntionne pas
			for($i = 0; $i < count($t_jsonTicket); $i++)
			{
			    $branchTicket = $branch_commit[$t_jsonTicket[$i]->patchset->tip];
			
			    echo "BrancheTicket = ".$branchTicket."\n";
			
			    foreach($tabBranchesCommits as $labranche => $commit)
			    {
				for($j = 0; $j < count($commit); $j++)
				{
				    if($t_jsonTicket[$i]->patchset->tip == $commit[$j])
				    {
					// echo "TROUVE !!!!!\n";
					echo "Commit = ".$t_jsonTicket[$i]->patchset->tip."\n";
					// echo $labranche." ".$commit[$j]."\n";
					// var_dump($commit);
					$t_changesets = array_merge( $t_changesets, $this->import_commits( $p_repo, $t_jsonTicket[$i], $labranche ) );
					break(2);
				    }
				    /*else
				    {
					echo 'a = '.$t_jsonTicket[$i]->patchset->tip."\n";
					var_dump($labranche);
					var_dump($commit);
					echo "BADDD\n";
				    }*/
				}
			    }
			}
		    }
		}
		
		// Pour recup les revisions (commits) on passe par la page log de la branche
		// https://localhost:8443/log/depot2.git/refs/heads/ticket/1
		
		/*
		$t_changesets = array();

		$t_changeset_table = plugin_table( 'changeset', 'Source' );

		foreach( $t_branches as $t_branch ) {
			$t_query = "SELECT parent FROM $t_changeset_table
				WHERE branch=" . db_param() .
				'ORDER BY timestamp ASC';
			$t_result = db_query_bound( $t_query, $t_branch );

			$t_commits = array( $t_branch->commit->id );

			if ( db_num_rows( $t_result ) > 0 ) {
				$t_parent = db_result( $t_result );
				echo "Oldest '$t_branch->name' branch parent: '$t_parent'\n";

				if ( !empty( $t_parent ) ) {
					$t_commits[] = $t_parent;
					echo "Parents not empty";
				}
			}

			$t_changesets = array_merge( $t_changesets, $this->import_commits( $p_repo, $t_commits, $t_branch ) );
		}*/

		echo '</pre>';

		return $t_changesets;
	}

	public function import_latest( $p_repo ) {
		return $this->import_full( $p_repo );
	}

	public function import_commits( $p_repo, $t_json, $p_branch ) {
		// repo de toutes
		// json d'un ticket
		// branche où se trouve le ticket

		// recup des config entree dans mantis
		$t_reponame = $p_repo->info['reponame'];
		$t_root = $p_repo->info['root'];
		
		//var_dump($t_json);

		$t_changesets = array();
		
		/*foreach($t_json as $key => $value)
		{
		    echo "Retrieving ".$key." ... \n";
		    
		    if(strtolower($key) == strtolower("fields") || strtolower($key) == strtolower("patchset"))
		    {
			foreach($value as $key2 => $value2)
			{
			    if(strtolower($key2) == strtolower("title"))
			      echo "Title = ".$value2."\n";
			    
			    if(strtolower($key2) == strtolower("tip"))
			      echo "Commit n° = ".$value2."\n";
			}
		    }
		}*/
		
		list( $t_changeset, $t_commit_parents ) = $this->json_commit_changeset( $p_repo, $t_json, $p_branch );
		if ( $t_changeset )
		{
		    $t_changesets[] = $t_changeset;
		}

		return $t_changesets;
	}

	private function json_commit_changeset( $p_repo, $p_json, $p_branch='' ) {
		// repo de tout
		// json = morceau d'un ticket = 1 commit
		// branche du ticket
		
		/*foreach($t_json as $key => $value)
		{
		    if(strtolower($key) == strtolower("fields") || strtolower($key) == strtolower("patchset"))
		    {
			foreach($value as $key2 => $value2)
			{
			    if(strtolower($key2) == strtolower("title"))
			      echo "Title = ".$value2."\n";
			    
			    if(strtolower($key2) == strtolower("tip"))
			      echo "Commit n° = ".$value2."\n";
			}
		    }
		}*/
		
		echo "processing ".$p_json->patchset->tip." on ".$p_branch." branch...  \n";
		//echo "type =".$p_json->patchset->type;
		
		//var_dump($p_repo);
		//var_dump($p_json);
		
		if ( !SourceChangeset::exists( $p_repo->id, $p_json->patchset->tip ) )
		{
			/*echo "repo ".$p_repo->id."\n";
			echo "revision ".$p_json[0]->patchset->tip."\n";
			echo "branche ".$p_branch."\n";
			echo "date ".date( 'Y-m-d H:i:s', strtotime( $p_json[0]->date ) )."\n";
			echo "auteur".$p_json[0]->author."\n";
			echo "nom ".$p_json[0]->fields->title."\n";*/
			
			if(!(strtolower($p_json->patchset->type) == strtolower("FastForward")))
			{
			    $message = $p_json->fields->title;
			}
			else
			{
			    $message = "FastForward";
			}
			
			//echo "message = ".$message ."\n";

			$t_changeset = new SourceChangeset(
				$p_repo->id,
				$p_json->patchset->tip,
				$p_branch,
				date( 'Y-m-d H:i:s', strtotime( $p_json->date ) ),
				$p_json->author,
				$message
			);
			
			//var_dump($p_json[0]->patchset->base);
			
			/*if(strtolower($p_json->patchset->type) == strtolower("FastForward"))
			//if(isset($p_json->fields->title))
			{
			  $t_changeset->message = $p_json->fields->title;
			}
			else
			{
			  $t_changeset->message = "FastForward";
			}*/
			

			if ( count( $p_json->patchset->base ) > 0 ) {
			    $t_changeset->parent = $p_json->patchset->base;
 			}
			else
			{
			    $t_changeset->parent = "";
			}

			//$t_changeset->message = $p_json->fields->title;
			$t_changeset->author_email = "email_author";
			$t_changeset->committer = $p_json->author;
			$t_changeset->committer_email = "email_commiter";

			if ( isset( $p_json->files ) ) {
				foreach ( $p_json->files as $t_file ) {
					switch ( $t_file->status ) {
						case 'added':
							$t_changeset->files[] = new SourceFile( 0, '', $t_file->filename, 'add' );
							break;
						case 'modified':
							$t_changeset->files[] = new SourceFile( 0, '', $t_file->filename, 'mod' );
							break;
						case 'removed':
							$t_changeset->files[] = new SourceFile( 0, '', $t_file->filename, 'rm' );
							break;
					}
				}
			}
			
			var_dump($t_changeset);

			$t_changeset->save();

			echo "saved.\n";
			return array( $t_changeset, $t_parents );
		} else {
			echo "already exists.\n";
			return array( null, array() );
		}
		/*else
		{
		    echo "FastForward !"."\n";
		}*/
	}

	private function oauth_authorize_uri( $p_repo ) {
		$t_blit_app_client_id = null;
		$t_blit_app_secret = null;
		$t_blit_app_access_token = null;

		if ( isset( $p_repo->info['blit_app_client_id'] ) ) {
			$t_blit_app_client_id = $p_repo->info['blit_app_client_id'];
		}

		if ( isset( $p_repo->info['blit_app_secret'] ) ) {
			$t_blit_app_secret = $p_repo->info['blit_app_secret'];
		}

		if ( !empty( $t_blit_app_client_id ) && !empty( $t_blit_app_secret ) ) {
			return 'https://github.com/login/oauth/authorize?client_id=' . $t_blit_app_client_id . '&redirect_uri=' . urlencode(config_get('path') . 'plugin.php?page=SourceGithub/oauth_authorize&id=' . $p_repo->id ) . '&scope=repo';
		} else {
			return '';
		}
	}

	public static function oauth_get_access_token( $p_repo, $p_code ) {
		# build the GitHub URL & POST data
		$t_url = 'https://github.com/login/oauth/access_token';
		$t_post_data = array( 'client_id' => $p_repo->info['blit_app_client_id'],
			'client_secret' => $p_repo->info['blit_app_secret'],
			'code' => $p_code );
		$t_data = self::url_post( $t_url, $t_post_data );

		$t_access_token = '';
		if ( !empty( $t_data ) ) {
			$t_response = array();
			parse_str( $t_data, $t_response );
			if ( isset( $t_response['access_token'] ) === true ) {
				$t_access_token = $t_response['access_token'];
			}
		}

		if ( !empty( $t_access_token ) ) {
			if ( $t_access_token != $p_repo->info['blit_app_access_token'] ) {
				$p_repo->info['blit_app_access_token'] = $t_access_token;
				$p_repo->save();
			}
			return true;
		} else {
			return false;
		}
	}

	public static function url_post( $p_url, $p_post_data ) {
		$t_post_data = http_build_query( $p_post_data );

		# Use the PHP cURL extension
		if( function_exists( 'curl_init' ) ) {
			$t_curl = curl_init( $p_url );
			curl_setopt( $t_curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $t_curl, CURLOPT_POST, true );
			curl_setopt( $t_curl, CURLOPT_POSTFIELDS, $t_post_data );

			$t_data = curl_exec( $t_curl );
			curl_close( $t_curl );

			return $t_data;
		} else {
			# Last resort system call
			$t_url = escapeshellarg( $p_url );
			$t_post_data = escapeshellarg( $t_post_data );
			return shell_exec( 'curl ' . $t_url . ' -d ' . $t_post_data );
		}
	}
}
