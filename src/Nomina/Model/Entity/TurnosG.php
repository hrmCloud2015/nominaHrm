<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class TurnosG extends TableGateway
{
    private $id;
    private $nombre;
    private $p1;
    private $p2;
    private $p3;
    private $p4;
    private $p5;
    private $p6;
    private $p7;
    private $p8;
    private $p9;
    private $p10;
    private $p11;
    private $p12;
    private $p13;
    private $p14;
    private $p15;
    
    

        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_turnos_g', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id      = $datos['id'];    
        $this->nombre      = $datos['nombre']; 
        $this->p1      = $datos['p1']==''?null:$datos['p1']; 
        $this->p2      = $datos['p2']==''?null:$datos['p2']; 
        $this->p3      = $datos['p3']==''?null:$datos['p3']; 
        $this->p4      = $datos['p4']==''?null:$datos['p4']; 
        $this->p5      = $datos['p5']==''?null:$datos['p5']; 
        $this->p6      = $datos['p6']==''?null:$datos['p6']; 
        $this->p7      = $datos['p7']==''?null:$datos['p7']; 
        $this->p8      = $datos['p8']==''?null:$datos['p8']; 
        $this->p9      = $datos['p9']==''?null:$datos['p9']; 
        $this->p10      = $datos['p10']==''?null:$datos['p10']; 
        $this->p11     = $datos['p11']==''?null:$datos['p11']; 
        $this->p12      = $datos['p12']==''?null:$datos['p12']; 
        $this->p13      = $datos['p13']==''?null:$datos['p13']; 
        $this->p14      = $datos['p14']==''?null:$datos['p14'];
        $this->p15      = $datos['p15']==''?null:$datos['p15'];
        
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
   
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }

     public function getNombresRegistroId($id)
    {
      $id  = (int) $id;
       return $this->getAdapter()->query('select b.codigo as x1,c.codigo as x2,
       d.codigo as x3,e.codigo as x4,f.codigo as x5,
       g.codigo as x6,h.codigo as x7, 
       i.codigo as x8,j.codigo as x9,
       k.codigo as x10,l.codigo as x11,m.codigo as x12,
       n.codigo as x13,o.codigo as x14 
       ,p.codigo as x15 
        from n_turnos_g a 

         left join n_turnos b on b.id=a.p1
         left join n_turnos c on c.id=a.p2
         left join n_turnos d on d.id=a.p3
         left join n_turnos e on e.id=a.p4
         left join n_turnos f on f.id=a.p5
         left join n_turnos g on g.id=a.p6
         left join n_turnos h on h.id=a.p7
         
         left join n_turnos i on i.id=a.p8
         left join n_turnos j on j.id=a.p9
         left join n_turnos k on k.id=a.p10
         left join n_turnos l on l.id=a.p11
         left join n_turnos m on m.id=a.p12
         left join n_turnos n on n.id=a.p13
         left join n_turnos o on o.id=a.p14
         
         left join n_turnos p on p.id=a.p15
        where a.id=? ',array($id))->current();


       return $row;
     } 
     
     public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $id = $this->id;
       $datos=array
       (
           'nombre'=>$this->nombre,
           'p1'=>  $this->p1,
           'p2'=>  $this->p2,
           'p3'=>  $this->p3,
           'p4'=>  $this->p4,
           'p5'=>  $this->p5,
           'p6'=>  $this->p6,
           'p7'=>  $this->p7,
           
           
        );
       if ($id==0) // Nuevo registro
       {
          $this->insert($datos);
          $inserted_id = $this->lastInsertValue;  
          return $inserted_id;          
       }          
       else // Mdificar registro
       {
          $this->update($datos, array('id' => $id));
          return $id;
       }
    }

}
?>
