<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class ConceptosV extends TableGateway
{
    private $id;
    private $nombre;
    private $idNom;
    private $idGrupo;    
    private $tipo;

        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_tip_matriz', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id      = $datos["id"];    
        $this->nombre  = $datos["nombre"]; 
        $this->idNom   = $datos["idTnom"]; 
        $this->idGrupo = $datos["idGrupo"]; 
        $this->tipo    = $datos["tipo"]; 
        
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $id = $this->id;
       $datos=array
       (
           'nombre'  => $this->nombre,
           'idTnom'  => $this->idNom,
           'idGrup'  => $this->idGrupo,
           'tipo'    => $this->tipo,
           'estado'    => $data['estado'],           
        );
       if ($id==0) // Nuevo registro
       { 
          $this->insert($datos);
          $inserted_id = $this->lastInsertValue;  
          return $inserted_id;          
       }
       else // Mdificar registro
          $this->update($datos, array('id' => $id));
    }
    
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
       if (!$row) {
          throw new \Exception("No hay registros asociados al valor $id");
       }
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }
}
?>
