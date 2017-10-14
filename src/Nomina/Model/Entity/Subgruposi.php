<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Subgruposi extends TableGateway
{
    private $id;

    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_subgrupos_e', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id = $datos["id"];
        
    }
    
    public function getRegistro($id)
    {
       $id  = (int) $id;
       $datos = $this->select(array('idTauto' => $id));
       return $datos->toArray();
     }     
    
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $datos=array
       (
           'idSub'  => $this->id,
           'idEmp'    => $data['idEmp'],

       );
       $this->insert($datos);       
    } 

    public function delRegistro($id)
    {
      $this->delete(array('id' => $id));               
    }
}
?>
