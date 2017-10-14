<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

use Principal\Model\LogFunc;

class Gnomina extends TableGateway
{
    private $id;
    private $idgrupo;
    private $tipo;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_nomina', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id       = $datos["id"];    
        $this->tipo     = $datos["tipo"];   
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array(),$fechai,$fechaf,$idCal,$idIcal,$dias,$idGrupo)
    {
       self::cargaAtributos($data);
       $id = $this->id;

       $t = new LogFunc($this->adapter);
       $dt = $t->getDatLog();

       $datos=array
       (
           'idGrupo' => $idGrupo,
           'idCal'   => $idCal,    
           'idIcal'  => $idIcal,
           'idTnom'  => $this->tipo,
           'fechaI'  => $fechai,
           'fechaF'  => $fechaf,
           'idUsu'   => $dt['idUsu'],
        );
        
//       if ($id==0) // Nuevo registro
          $this->insert($datos);
          $inserted_id = $this->lastInsertValue;  
          return $inserted_id;              
 //      else // Mdificar registro
 //         $this->update($datos, array('id' => $id));
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
