<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Principal\Model\LogFunc; // Traer datos de session activa y datos del pc 


class Asalarial extends TableGateway
{
    private $id;
    private $estado;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_asalarial', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id     = $datos["id"];    
        $this->estado = $datos["estado"];   
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro( $data=array() , $escala )
    {
       self::cargaAtributos($data);
       // Datos de transaccion
       $t = new LogFunc($this->adapter);
       $dt = $t->getDatLog();  
       $datos=array
       (
           'fecDoc'  => $dt['fecSis'],
           'estado'  => $data['estado'],
           'escala'  => $escala, // 0 no manjea escala, 1 si maneja escala 
           'idGrup'  => $data['idGrupo'],                      
           'idPerI'  => $data['tipo1'],                      
           'idPerF'  => $data['tipo2'],                      
           'idPerA'  => $data['tipo3'],                      
           'idUsu'   => $dt['idUsu'],           
        );
       if ($data['id']==0)
       {
         $this->insert($datos);
         $inserted_id = $this->lastInsertValue;  
         return $inserted_id;              
       }else{
         $this->update(array('id' => $data['id'] ));  
         return 0;             
       }

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
